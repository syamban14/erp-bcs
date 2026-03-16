<?php

namespace App\Filament\Concerns;

/**
 * Trait SuperAdminOnly
 *
 * Gunakan trait ini di Filament Resource yang hanya boleh diakses oleh role 'superadmin'.
 * Stakeholder dan role lainnya tidak akan melihat menu ini di navigasi.
 *
 * Usage:
 *   use App\Filament\Concerns\SuperAdminOnly;
 *   class SomeResource extends Resource {
 *       use SuperAdminOnly;
 *   }
 */
trait SuperAdminOnly
{
    /**
     * Sembunyikan menu dari navigasi jika bukan superadmin
     */
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'superadmin';
    }

    /**
     * Blokir akses view detail jika bukan superadmin
     */
    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->role === 'superadmin';
    }

    /**
     * Blokir akses create jika bukan superadmin
     */
    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->role === 'superadmin';
    }

    /**
     * Blokir akses edit jika bukan superadmin
     */
    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->role === 'superadmin';
    }

    /**
     * Blokir akses delete jika bukan superadmin
     */
    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->role === 'superadmin';
    }
}
