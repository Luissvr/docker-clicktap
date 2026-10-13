<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

trait TracksCatalogChanges
{
    public static function bootTracksCatalogChanges(): void
    {
        static::creating(function (Model $record): void {
            $record->revision = 1;
            $record->creado_por = auth()->id();
            $record->modificado_por = auth()->id();
        });
        static::updating(function (Model $record): void {
            $record->revision = ((int) $record->getRawOriginal('revision')) + 1;
            $record->modificado_por = auth()->id() ?? $record->modificado_por;
        });
    }
}
