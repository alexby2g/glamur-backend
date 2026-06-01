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
        'monto_efectivo',
        'monto_qr',
        'monto_transferencia',
        'metodo',
        'estado',
        'fecha_pago',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_efectivo' => 'decimal:2',
        'monto_qr' => 'decimal:2',
        'monto_transferencia' => 'decimal:2',
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

    public function getDetalleMetodoAttribute()
    {
        if ($this->metodo === 'mixto') {
            return 'Efectivo Bs ' . number_format((float) $this->monto_efectivo, 2) .
                ' + QR Bs ' . number_format((float) $this->monto_qr, 2) .
                ' + Transferencia Bs ' . number_format((float) $this->monto_transferencia, 2);
        }

        if ($this->metodo === 'qr') {
            return 'QR';
        }

        if ($this->metodo === 'transferencia') {
            return 'Transferencia';
        }

        return 'Efectivo';
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