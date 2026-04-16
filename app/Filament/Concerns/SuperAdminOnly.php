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
        if (!auth()->check()) return false;
        $user = auth()->user();
        return in_array($user->role, ['superadmin', 'superhyperadmin']) 
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'superhyperadmin']));
    }

    /**
     * Blokir akses view detail jika bukan superadmin
     */
    public static function canView($record): bool
    {
        if (!auth()->check()) return false;
        $user = auth()->user();
        return in_array($user->role, ['superadmin', 'superhyperadmin']) 
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'superhyperadmin']));
    }

    /**
     * Blokir akses create jika bukan superadmin
     */
    public static function canCreate(): bool
    {
        if (!auth()->check()) return false;
        $user = auth()->user();
        return in_array($user->role, ['superadmin', 'superhyperadmin']) 
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'superhyperadmin']));
    }

    /**
     * Blokir akses edit jika bukan superadmin
     */
    public static function canEdit($record): bool
    {
        if (!auth()->check()) return false;
        $user = auth()->user();
        return in_array($user->role, ['superadmin', 'superhyperadmin']) 
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'superhyperadmin']));
    }

    /**
     * Blokir akses delete jika bukan superadmin
     */
    public static function canDelete($record): bool
    {
        if (!auth()->check()) return false;
        $user = auth()->user();
        return in_array($user->role, ['superadmin', 'superhyperadmin']) 
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'superhyperadmin']));
    }
}
