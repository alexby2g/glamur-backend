<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Empleado;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\SyncOperation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncController extends Controller
{
    private const TYPES = ['clientes', 'empleados', 'servicios', 'citas', 'pagos'];

    public function pull(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'since' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'La fecha de sincronización no es válida.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $serverTime = now('UTC');
        $since = $request->filled('since') ? Carbon::parse($request->input('since'))->utc() : null;
        $usuario = $request->attributes->get('usuario_sistema');
        $esEmpleado = $request->attributes->get('usuario_es_empleado') === true;
        $empleadoId = $request->attributes->get('usuario_empleado_id');

        $allEmployeeCitaIds = collect();
        $allEmployeeClienteIds = collect();
        if ($esEmpleado) {
            $scope = Cita::withTrashed()->where('empleado_id', $empleadoId ?: -1);
            $allEmployeeCitaIds = (clone $scope)->pluck('id');
            $allEmployeeClienteIds = (clone $scope)->pluck('cliente_id')->filter()->unique();
        }

        $citasQuery = Cita::withTrashed()->with(['cliente', 'empleado']);
        if ($esEmpleado) {
            $citasQuery->where('empleado_id', $empleadoId ?: -1);
        }
        $this->changedSince($citasQuery, $since);
        $citas = $citasQuery->get();

        $clientesQuery = Cliente::withTrashed();
        if ($esEmpleado) {
            $clientesQuery->whereIn('id', $allEmployeeClienteIds);
        }
        $this->changedSince($clientesQuery, $since);

        $empleadosQuery = Empleado::withTrashed();
        if ($esEmpleado) {
            $empleadosQuery->where('id', $empleadoId ?: -1);
        }
        $this->changedSince($empleadosQuery, $since);

        $serviciosQuery = Servicio::withTrashed();
        $this->changedSince($serviciosQuery, $since);

        $pagosQuery = Pago::withTrashed()->with('cita.cliente');
        if ($esEmpleado) {
            $pagosQuery->whereIn('cita_id', $allEmployeeCitaIds);
        }
        $this->changedSince($pagosQuery, $since);

        return response()->json([
            'server_time' => $serverTime->toIso8601String(),
            'user_id' => $usuario?->id,
            'data' => [
                'clientes' => $clientesQuery->get()->map(fn (Cliente $item) => $this->serialize($item))->values(),
                'empleados' => $empleadosQuery->get()->map(fn (Empleado $item) => $this->serialize($item))->values(),
                'servicios' => $serviciosQuery->get()->map(fn (Servicio $item) => $this->serialize($item))->values(),
                'citas' => $citas->map(fn (Cita $item) => $this->serializeCita($item))->values(),
                'pagos' => $pagosQuery->get()->map(fn (Pago $item) => $this->serializePago($item))->values(),
                'configuracion' => Configuracion::query()->first()?->toArray() ?? [],
            ],
        ]);
    }

    public function push(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'operations' => 'required|array|max:100',
            'operations.*.operation_id' => 'required|uuid',
            'operations.*.entity_type' => 'required|in:' . implode(',', self::TYPES),
            'operations.*.entity_uuid' => 'required|uuid',
            'operations.*.action' => 'required|in:create,update,delete,restore',
            'operations.*.payload' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'El paquete de sincronización no es válido.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $results = [];
        foreach ($request->input('operations', []) as $operation) {
            $results[] = $this->runOperation($request, $operation);
        }

        return response()->json([
            'server_time' => now('UTC')->toIso8601String(),
            'results' => $results,
        ]);
    }

    private function runOperation(Request $request, array $operation): array
    {
        $cached = SyncOperation::query()->where('operation_id', $operation['operation_id'])->first();
        if ($cached && is_array($cached->response)) {
            return $cached->response;
        }

        try {
            return DB::transaction(function () use ($request, $operation) {
                $this->authorizeOperation($request, $operation);
                $entity = $this->applyOperation($operation);
                $result = [
                    'operation_id' => $operation['operation_id'],
                    'entity_type' => $operation['entity_type'],
                    'entity_uuid' => $operation['entity_uuid'],
                    'status' => 'ok',
                    'entity' => $entity,
                ];

                SyncOperation::create([
                    'operation_id' => $operation['operation_id'],
                    'usuario_sistema_id' => $request->attributes->get('usuario_sistema')?->id,
                    'entity_type' => $operation['entity_type'],
                    'entity_uuid' => $operation['entity_uuid'],
                    'action' => $operation['action'],
                    'response' => $result,
                ]);

                return $result;
            });
        } catch (ValidationException $error) {
            return $this->errorResult($operation, $error->validator->errors()->first() ?: 'Datos inválidos.');
        } catch (ModelNotFoundException $error) {
            return $this->errorResult($operation, 'No se encontró el registro relacionado. Sincroniza e inténtalo nuevamente.');
        } catch (\Throwable $error) {
            report($error);
            return $this->errorResult($operation, 'No se pudo procesar la operación en el servidor.');
        }
    }

    private function authorizeOperation(Request $request, array $operation): void
    {
        if ($request->attributes->get('usuario_es_admin') === true) {
            return;
        }

        $empleadoId = $request->attributes->get('usuario_empleado_id');
        $allowed = $operation['entity_type'] === 'citas' && $operation['action'] === 'update';
        $cita = Cita::withTrashed()->where('uuid', $operation['entity_uuid'])->first();

        if (!$allowed || !$cita || (int) $cita->empleado_id !== (int) $empleadoId) {
            throw new \RuntimeException('No tienes permiso para sincronizar esta operación.');
        }
    }

    private function applyOperation(array $operation): array
    {
        $type = $operation['entity_type'];
        $action = $operation['action'];
        $uuid = $operation['entity_uuid'];
        $payload = $operation['payload'] ?? [];

        if ($action === 'delete') {
            return $this->deleteEntity($type, $uuid);
        }

        if ($action === 'restore') {
            return $this->restoreEntity($type, $uuid);
        }

        return match ($type) {
            'clientes' => $this->saveCliente($uuid, $payload),
            'empleados' => $this->saveEmpleado($uuid, $payload),
            'servicios' => $this->saveServicio($uuid, $payload),
            'citas' => $this->saveCita($uuid, $payload),
            'pagos' => $this->savePago($uuid, $payload),
        };
    }

    private function saveCliente(string $uuid, array $payload): array
    {
        $values = Validator::make($payload, [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ])->validate();
        $model = Cliente::withTrashed()->firstOrNew(['uuid' => $uuid]);
        if ($model->trashed()) $model->restore();
        $model->fill($values)->save();
        return $this->serialize($model->fresh());
    }

    private function saveEmpleado(string $uuid, array $payload): array
    {
        $values = Validator::make($payload, [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'ci' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'cargo' => 'nullable|string|max:100',
            'especialidad' => 'nullable|string|max:150',
            'comision_porcentaje' => 'nullable|numeric|min:0|max:100',
            'salario_base' => 'nullable|numeric|min:0',
            'direccion' => 'nullable|string|max:255',
            'fecha_ingreso' => 'nullable|date',
            'activo' => 'nullable|boolean',
            'observaciones' => 'nullable|string',
        ])->validate();
        $model = Empleado::withTrashed()->firstOrNew(['uuid' => $uuid]);
        if ($model->trashed()) $model->restore();
        $model->fill($values)->save();
        return $this->serialize($model->fresh());
    }

    private function saveServicio(string $uuid, array $payload): array
    {
        $values = Validator::make($payload, [
            'categoria' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'activo' => 'nullable|boolean',
        ])->validate();
        $model = Servicio::withTrashed()->firstOrNew(['uuid' => $uuid]);
        if ($model->trashed()) $model->restore();
        $model->fill($values)->save();
        return $this->serialize($model->fresh());
    }

    private function saveCita(string $uuid, array $payload): array
    {
        $values = Validator::make($payload, [
            'cliente_uuid' => 'required|uuid',
            'empleado_uuid' => 'nullable|uuid',
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'servicio' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'estado' => 'nullable|in:pendiente,concluida,cancelada',
            'estado_pago' => 'nullable|in:pendiente,pagado',
            'metodo_pago' => 'nullable|string|max:50',
        ])->validate();

        $cliente = Cliente::query()->where('uuid', $values['cliente_uuid'])->firstOrFail();
        $empleado = !empty($values['empleado_uuid'])
            ? Empleado::query()->where('uuid', $values['empleado_uuid'])->firstOrFail()
            : null;
        $model = Cita::withTrashed()->firstOrNew(['uuid' => $uuid]);
        if ($model->trashed()) $model->restore();

        $collision = Cita::query()
            ->whereDate('fecha', $values['fecha'])
            ->where('hora', $values['hora'])
            ->when($model->exists, fn ($query) => $query->where('id', '!=', $model->id))
            ->exists();
        if ($collision) {
            throw ValidationException::withMessages(['hora' => 'Ya existe otra cita en esa fecha y hora.']);
        }

        $model->fill([
            'cliente_id' => $cliente->id,
            'empleado_id' => $empleado?->id,
            'fecha' => $values['fecha'],
            'hora' => $values['hora'],
            'servicio' => trim($values['servicio']),
            'precio' => $values['precio'],
            'estado' => $values['estado'] ?? ($model->estado ?: 'pendiente'),
            'estado_pago' => $values['estado_pago'] ?? ($model->estado_pago ?: 'pendiente'),
            'metodo_pago' => $values['metodo_pago'] ?? $model->metodo_pago,
        ])->save();

        return $this->serializeCita($model->fresh(['cliente', 'empleado']));
    }

    private function savePago(string $uuid, array $payload): array
    {
        $values = Validator::make($payload, [
            'cita_uuid' => 'required|uuid',
            'metodo' => 'required|in:efectivo,qr,transferencia,mixto',
            'monto' => 'required|numeric|min:0.01',
            'monto_efectivo' => 'nullable|numeric|min:0',
            'monto_qr' => 'nullable|numeric|min:0',
            'monto_transferencia' => 'nullable|numeric|min:0',
            'fecha_pago' => 'nullable|date',
        ])->validate();
        $cita = Cita::query()->where('uuid', $values['cita_uuid'])->firstOrFail();
        $model = Pago::withTrashed()->firstOrNew(['uuid' => $uuid]);
        if ($model->trashed()) $model->restore();
        $model->fill([
            'cita_id' => $cita->id,
            'cliente_id' => $cita->cliente_id,
            'monto' => $values['monto'],
            'monto_efectivo' => $values['monto_efectivo'] ?? 0,
            'monto_qr' => $values['monto_qr'] ?? 0,
            'monto_transferencia' => $values['monto_transferencia'] ?? 0,
            'metodo' => $values['metodo'],
            'estado' => 'pagado',
            'fecha_pago' => $values['fecha_pago'] ?? now('America/La_Paz'),
        ])->save();
        $this->updateCitaPayment($cita, $values['metodo']);
        return $this->serializePago($model->fresh('cita.cliente'));
    }

    private function deleteEntity(string $type, string $uuid): array
    {
        $model = $this->findModel($type, $uuid);
        if (!$model->trashed()) $model->delete();
        if ($type === 'pagos') {
            $cita = Cita::withTrashed()->find($model->cita_id);
            if ($cita) $this->updateCitaPayment($cita, $cita->metodo_pago);
        }
        if ($type === 'citas') {
            $model->load(['cliente', 'empleado']);
            return $this->serializeCita($model);
        }
        if ($type === 'pagos') {
            $model->load('cita.cliente');
            return $this->serializePago($model);
        }
        return $this->serialize($model);
    }

    private function restoreEntity(string $type, string $uuid): array
    {
        $model = $this->findModel($type, $uuid);
        if ($model->trashed()) $model->restore();
        return $type === 'citas'
            ? $this->serializeCita($model->fresh(['cliente', 'empleado']))
            : ($type === 'pagos'
                ? $this->serializePago($model->fresh('cita.cliente'))
                : $this->serialize($model->fresh()));
    }

    private function findModel(string $type, string $uuid): Model
    {
        $class = match ($type) {
            'clientes' => Cliente::class,
            'empleados' => Empleado::class,
            'servicios' => Servicio::class,
            'citas' => Cita::class,
            'pagos' => Pago::class,
        };
        return $class::withTrashed()->where('uuid', $uuid)->firstOrFail();
    }

    private function updateCitaPayment(Cita $cita, ?string $method): void
    {
        $total = (float) $cita->pagos()->sum('monto');
        $paid = (float) $cita->precio <= 0 ? $total > 0 : $total >= (float) $cita->precio;
        $cita->update([
            'estado_pago' => $paid ? 'pagado' : 'pendiente',
            'metodo_pago' => $method,
        ]);
    }

    private function serialize(Model $model): array
    {
        return $model->toArray();
    }

    private function serializeCita(Cita $cita): array
    {
        $data = $cita->toArray();
        $data['cliente_uuid'] = $cita->cliente?->uuid;
        $data['empleado_uuid'] = $cita->empleado?->uuid;
        return $data;
    }

    private function serializePago(Pago $pago): array
    {
        $data = $pago->toArray();
        $cita = $pago->cita ?: Cita::withTrashed()->with('cliente')->find($pago->cita_id);
        $data['cita_uuid'] = $cita?->uuid;
        $data['cliente_uuid'] = $cita?->cliente?->uuid;
        return $data;
    }

    private function changedSince($query, ?Carbon $since): void
    {
        if ($since) {
            $query->where('updated_at', '>', $since);
        }
    }

    private function errorResult(array $operation, string $message): array
    {
        return [
            'operation_id' => $operation['operation_id'],
            'entity_type' => $operation['entity_type'],
            'entity_uuid' => $operation['entity_uuid'],
            'status' => 'error',
            'message' => $message,
        ];
    }
}
