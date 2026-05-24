<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cita;
use App\Models\Pago;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'telefono',
        'email',
    ];

    // 🔥 RELACIÓN CITAS
    public function citas()
    {
        return $this->hasMany(Cita::class);
    }

    // 🔥 RELACIÓN PAGOS
    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }
}