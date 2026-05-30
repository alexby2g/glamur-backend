<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empleado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'empleados';

    protected $fillable = [
        'nombre',
        'telefono',
        'ci',
        'email',
        'cargo',
        'especialidad',
        'comision_porcentaje',
        'salario_base',
        'direccion',
        'fecha_ingreso',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'comision_porcentaje' => 'decimal:2',
        'salario_base' => 'decimal:2',
        'fecha_ingreso' => 'date',
    ];

    public function citas()
    {
        return $this->hasMany(Cita::class, 'empleado_id');
    }

    public function cuentaSistema()
    {
        return $this->hasOne(UsuarioSistema::class, 'empleado_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function getEstadoTextoAttribute()
    {
        return $this->activo ? 'Activo' : 'Inactivo';
    }

    public function calcularComision($totalGenerado = 0)
    {
        $porcentaje = (float) $this->comision_porcentaje;
        $total = (float) $totalGenerado;

        return round(($total * $porcentaje) / 100, 2);
    }
}