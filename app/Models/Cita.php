<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cita extends Model
{
    use SoftDeletes;

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

    public function cliente()
    {
        return $this->belongsTo(\App\Models\Cliente::class)->withTrashed();
    }

    public function pagos()
    {
        return $this->hasMany(\App\Models\Pago::class);
    }
}