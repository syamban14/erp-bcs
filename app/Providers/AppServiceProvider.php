<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            // Bypass Gate authorization otomatis untuk roles khusus (Menggunakan Spatie Permission)
            if ($user->hasAnyRole(['superadmin', 'superhyperadmin'])) {
                return true;
            }
            
            // Bypass Gate authorization otomatis untuk email-email direksi/owner di server
            if (in_array(strtolower($user->email), [
                'windyriche@gmail.com',
                'rizkyfiqi4@gmail.com'
            ])) {
                return true;
            }
            
            return null;
        });

        // Otorisasi eksklusif Opcodes Log Viewer
        \Illuminate\Support\Facades\Gate::define('viewLogViewer', function ($user) {
            return $user->hasRole('superhyperadmin');
        });

        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);
        
        // Register Model Observers for automatic notifications
        \App\Models\Leave::observe(\App\Observers\LeaveObserver::class);
        \App\Models\PermissionRequest::observe(\App\Observers\PermissionRequestObserver::class);
        \App\Models\OvertimeRequest::observe(\App\Observers\OvertimeRequestObserver::class);
        \App\Models\OutstationRequest::observe(\App\Observers\OutstationRequestObserver::class);
        \App\Models\Loan::observe(\App\Observers\LoanObserver::class);
        // Sinkronkan kuota cuti otomatis saat tgl_masuk karyawan diubah
        \App\Models\MKaryawan::observe(\App\Observers\MKaryawanObserver::class);
        
        // Inject approval actions JavaScript into Filament pages
        if (class_exists(\Filament\Facades\Filament::class)) {
            \Filament\Facades\Filament::serving(function () {
                \Filament\Facades\Filament::registerRenderHook(
                    'body.end',
                    fn (): string => view('filament.approval-actions')->render()
                );
            });
        }
    }
}
