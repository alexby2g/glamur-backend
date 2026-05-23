<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $fillable = [
        'cita_id',
        'monto',
        'metodo',
        'estado',
        'fecha_pago'
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class);
    }
}