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

        // Roles yang dapat melihat seluruh data secara global
        if (in_array($user->role, ['superadmin', 'hr', 'direktur'])) {
            return $query;
        }

        // Supervisor dan Manager hanya boleh melihat data dari bawahan mereka
        $karyawan = $user->karyawan;
        if (!$karyawan) {
            // Jika akun Atasan tidak di-mapping ke M_Karyawan, tidak bisa melihat apa-apa
            return $query->where('id', 0);
        }

        // Cari semua title bawahan dari user yang sedang login
        $bawahanTitles = \App\Models\MAtasan::where('title_atasan', $karyawan->title)
            ->pluck('title_bawahan');

        // Cari ID user (MPresensi) dari semua bawahan tersebut
        $bawahanUserIds = \App\Models\MPresensi::whereHas('karyawan', function($q) use ($bawahanTitles) {
            $q->whereIn('title', $bawahanTitles);
        })->pluck('id');

        // Tambahkan ID sendiri agar dia tetap bisa melihat request miliknya sendiri jika diperlukan,
        // (opsional), jika diapply ke Resource di mana Atasan juga bisa submit dan melihat historynya sendiri
        $bawahanUserIds->push($user->id);

        // Filter tabel utama berdasarkan daftar ID bawahan (termasuk dirinya sendiri)
        // Note: Pastikan tabel resource ini memiliki field `user_id` atau `requester_id`
        // Secara default menggunakan `user_id`
        $userColumn = 'user_id';
        if (property_exists(static::class, 'subordinateFilterColumn')) {
            $userColumn = static::$subordinateFilterColumn;
        }

        return $query->whereIn($userColumn, $bawahanUserIds);
    }
}
