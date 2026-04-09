<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait FiltersBySubordinates
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if (!$user) {
            return $query;
        }

        // Jika global admin, kembalikan semua data
        if ($user->isGlobalAdmin()) {
            return $query;
        }

        // Gunakan fungsi recursive pencarian bawahan multi-level yang baru kita buat
        $bawahanUserIds = collect($user->getSubordinateUserIds());

        // Tambahkan ID sendiri agar dia tetap bisa melihat request miliknya sendiri jika diperlukan
        $bawahanUserIds->push($user->id);

        $userColumn = property_exists(static::class, 'subordinateFilterColumn') 
            ? static::$subordinateFilterColumn 
            : 'user_id';

        return $query->whereIn($userColumn, $bawahanUserIds->unique()->toArray());
    }
}
