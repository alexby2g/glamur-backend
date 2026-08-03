<?php

namespace App\Http\Middleware;

use App\Models\Auditoria;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RegistrarAuditoria
{
    private array $metodosAuditables = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private array $camposSensibles = [
        'password',
        'password_confirmation',
        'token',
        'codigo_registro',
        'authorization',
        'foto',
        'foto_perfil',
        'imagen',
        'archivo',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            in_array($request->method(), $this->metodosAuditables, true)
            && $response->getStatusCode() >= 200
            && $response->getStatusCode() < 400
        ) {
            $this->registrar($request, $response);
        }

        return $response;
    }

    private function registrar(Request $request, Response $response): void
    {
        try {
            $usuario = $request->attributes->get('usuario_sistema');
            $segmentos = $request->segments();
            $modulo = $segmentos[1] ?? $segmentos[0] ?? 'sistema';

            Auditoria::create([
                'usuario_sistema_id' => $usuario?->id,
                'usuario_nombre' => $usuario?->nombre ?? $usuario?->usuario,
                'usuario_rol' => $request->attributes->get('usuario_rol'),
                'accion' => $this->accion($request),
                'metodo' => $request->method(),
                'modulo' => $modulo,
                'ruta' => '/' . ltrim($request->path(), '/'),
                'entidad_id' => $request->route('id') ?? $request->route('cita_id'),
                'datos' => $this->datosSeguros($request),
                'ip' => $request->ip(),
                'dispositivo' => mb_substr((string) $request->userAgent(), 0, 500),
                'codigo_respuesta' => $response->getStatusCode(),
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe interrumpir una operación válida del negocio.
            report($e);
        }
    }

    private function datosSeguros(Request $request): ?array
    {
        $datos = Arr::except($request->all(), $this->camposSensibles);

        return empty($datos) ? null : $this->limitar($datos);
    }

    private function limitar(array $datos): array
    {
        return collect($datos)->map(function ($valor) {
            if (is_array($valor)) {
                return $this->limitar($valor);
            }

            if (is_string($valor) && mb_strlen($valor) > 500) {
                return mb_substr($valor, 0, 500) . '…';
            }

            return $valor;
        })->all();
    }

    private function accion(Request $request): string
    {
        if ($request->isMethod('delete')) {
            return 'eliminar';
        }

        if ($request->isMethod('post')) {
            return 'crear';
        }

        return 'actualizar';
    }
}
