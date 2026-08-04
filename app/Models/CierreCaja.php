<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreCaja extends Model
{
    protected $table = 'cierres_caja';

    protected $fillable = [
        'fecha', 'estado', 'fondo_inicial', 'total_efectivo', 'total_qr',
        'total_transferencia', 'total_otros', 'total_cobrado',
        'efectivo_esperado', 'efectivo_contado', 'diferencia', 'observacion',
        'abierto_por', 'cerrado_por', 'abierta_at', 'cerrada_at',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'fondo_inicial' => 'decimal:2',
        'total_efectivo' => 'decimal:2',
        'total_qr' => 'decimal:2',
        'total_transferencia' => 'decimal:2',
        'total_otros' => 'decimal:2',
        'total_cobrado' => 'decimal:2',
        'efectivo_esperado' => 'decimal:2',
        'efectivo_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'abierta_at' => 'datetime',
        'cerrada_at' => 'datetime',
    ];

    public function usuarioApertura()
    {
        return $this->belongsTo(UsuarioSistema::class, 'abierto_por');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(UsuarioSistema::class, 'cerrado_por');
    }
}
