<?php

namespace App\Http\Middleware;

use App\Models\Auditoria;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RegistrarAuditoria
{
    private array $metodosAuditables = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private array $camposSensibles = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'codigo_registro',
        'authorization',
        'api_key',
        'secret',
        'foto',
        'foto_perfil',
        'imagen',
        'archivo',
    ];

    private array $fragmentosSensibles = [
        'password',
        'passwd',
        'token',
        'authorization',
        'api_key',
        'apikey',
        'secret',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            in_array($request->method(), $this->metodosAuditables, true)
            && !$request->is('api/logout', 'api/logout-all')
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
        $datos = $this->filtrarYLimitar($request->all());

        return empty($datos) ? null : $datos;
    }

    private function filtrarYLimitar(array $datos): array
    {
        $resultado = [];

        foreach ($datos as $clave => $valor) {
            if ($this->esCampoSensible((string) $clave)) {
                continue;
            }

            if (is_array($valor)) {
                $resultado[$clave] = $this->filtrarYLimitar($valor);
                continue;
            }

            $resultado[$clave] = is_string($valor) && mb_strlen($valor) > 500
                ? mb_substr($valor, 0, 500) . '…'
                : $valor;
        }

        return $resultado;
    }

    private function esCampoSensible(string $clave): bool
    {
        $clave = strtolower($clave);

        if (in_array($clave, $this->camposSensibles, true)) {
            return true;
        }

        foreach ($this->fragmentosSensibles as $fragmento) {
            if (str_contains($clave, $fragmento)) {
                return true;
            }
        }

        return false;
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
