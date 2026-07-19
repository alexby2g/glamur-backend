<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasSyncUuid;

class Servicio extends Model
{
    use HasFactory, SoftDeletes, HasSyncUuid;

    protected $table = 'servicios';

    protected $fillable = [
        'uuid',
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
