<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuraciones';

    protected $fillable = [
        'nombre_negocio',
        'nombre_corto',
        'slogan',
        'telefono',
        'whatsapp',
        'direccion',
        'mensaje_whatsapp',
        'logo_url',
        'moneda',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
