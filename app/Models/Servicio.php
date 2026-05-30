<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicios';

    protected $fillable = [
        'grupo',
        'categoria',
        'nombre',
        'descripcion',
        'precio',
        'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function getPrecioFormateadoAttribute()
    {
        return 'Bs ' . number_format((float) $this->precio, 2, ',', '.');
    }
}