<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $fillable = [
        'usuario_sistema_id',
        'usuario_nombre',
        'usuario_rol',
        'accion',
        'metodo',
        'modulo',
        'ruta',
        'entidad_id',
        'datos',
        'ip',
        'dispositivo',
        'codigo_respuesta',
    ];

    protected $casts = [
        'datos' => 'array',
    ];
}
