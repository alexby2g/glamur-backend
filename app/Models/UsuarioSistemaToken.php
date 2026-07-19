<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioSistemaToken extends Model
{
    protected $fillable = [
        'usuario_sistema_id',
        'device_id',
        'device_name',
        'token_hash',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(UsuarioSistema::class, 'usuario_sistema_id');
    }
}
