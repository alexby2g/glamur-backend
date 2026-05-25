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
        'deleted_at' => 'datetime',
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class)->withTrashed();
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }
}