<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasSyncUuid
{
    protected static function bootHasSyncUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
