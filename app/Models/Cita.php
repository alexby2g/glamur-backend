<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cita extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'empleado_id',
        'fecha',
        'hora',
        'servicio',
        'estado',
        'precio',
        'estado_pago',
        'metodo_pago'
    ];

    protected $casts = [
        'fecha' => 'date',
        'precio' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(\App\Models\Cliente::class)->withTrashed();
    }

    public function empleado()
    {
        return $this->belongsTo(\App\Models\Empleado::class)->withTrashed();
    }

    public function pagos()
    {
        return $this->hasMany(\App\Models\Pago::class);
    }

    public function pagosPagados()
    {
        return $this->hasMany(\App\Models\Pago::class)->where('estado', 'pagado');
    }

    public function getMontoPagadoAttribute()
    {
        return $this->pagos()
            ->where('estado', 'pagado')
            ->sum('monto');
    }

    public function getBaseComisionAttribute()
    {
        $montoPagado = (float) $this->monto_pagado;

        if ($montoPagado > 0) {
            return $montoPagado;
        }

        if ($this->estado_pago === 'pagado') {
            return (float) $this->precio;
        }

        return 0;
    }

    public function getComisionEmpleadoAttribute()
    {
        if (!$this->empleado) {
            return 0;
        }

        return $this->empleado->calcularComision($this->base_comision);
    }
}