<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pagos';

    protected $fillable = [
        'cita_id',
        'cliente_id',
        'monto',
        'metodo',
        'estado',
        'fecha_pago',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_pago' => 'datetime',
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empleado()
    {
        return $this->hasOneThrough(
            Empleado::class,
            Cita::class,
            'id',
            'id',
            'cita_id',
            'empleado_id'
        );
    }

    public function getComisionEmpleadoAttribute()
    {
        if (!$this->cita || !$this->cita->empleado) {
            return 0;
        }

        if ($this->estado !== 'pagado') {
            return 0;
        }

        return $this->cita->empleado->calcularComision($this->monto);
    }
}