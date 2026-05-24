<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Cita;
use App\Models\Pago;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'telefono',
        'email',
    ];

    // ==========================
    // RELACIÓN CON CITAS ACTIVAS
    // ==========================
    public function citas()
    {
        return $this->hasMany(Cita::class);
    }

    // ======================================
    // RELACIÓN CON CITAS INCLUYENDO BORRADAS
    // Útil para historial / recuperación
    // ======================================
    public function todasLasCitas()
    {
        return $this->hasMany(Cita::class)->withTrashed();
    }

    // =====================================================
    // RELACIÓN DIRECTA CON PAGOS
    // Solo funciona si pagos.cliente_id tiene valor.
    // En tu caso actual algunos pagos tienen cliente_id null.
    // =====================================================
    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    // =====================================================
    // RELACIÓN REAL DE PAGOS POR MEDIO DE CITAS
    // Cliente -> Citas -> Pagos
    // Esta es la más importante para tu sistema actual.
    // =====================================================
    public function pagosPorCitas()
    {
        return $this->hasManyThrough(
            Pago::class,
            Cita::class,
            'cliente_id',
            'cita_id',
            'id',
            'id'
        );
    }
}