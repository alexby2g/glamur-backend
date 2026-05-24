<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    protected $fillable = [
        'cliente_id',
        'fecha',
        'hora',
        'servicio',
        'estado',
        'precio',
        'estado_pago',
        'metodo_pago'
    ];

    // 🔥 RELACIÓN CLIENTE
    public function cliente()
    {
        return $this->belongsTo(\App\Models\Cliente::class);
    }

    // 🔥 RELACIÓN PAGOS
    public function pagos()
    {
        return $this->hasMany(\App\Models\Pago::class);
    }
}