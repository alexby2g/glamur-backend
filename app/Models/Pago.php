<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Cita;
use App\Models\Cliente;

class Pago extends Model
{
    protected $fillable = [
        'cliente_id',
        'cita_id',
        'monto',
        'metodo',
        'estado',
        'fecha_pago'
    ];

    // 🔥 RELACIÓN CITA
    public function cita()
    {
        return $this->belongsTo(Cita::class);
    }

    // 🔥 RELACIÓN CLIENTE
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}