<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncOperation extends Model
{
    protected $fillable = [
        'operation_id',
        'usuario_sistema_id',
        'entity_type',
        'entity_uuid',
        'action',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
    ];
}
