<?php

namespace App\Console\Commands;

use App\Models\MPresensi;
use App\Models\MKaryawan;
use Illuminate\Console\Command;

class SyncKaryawanId extends Command
{
    protected $signature = 'sync:karyawan-id';
    protected $description = 'Sync karyawan_id in m_presensi based on matching email';

    public function handle()
    {
        $this->info('Starting sync karyawan_id...');
        
        // Get all users with NULL karyawan_id
        $users = MPresensi::whereNull('karyawan_id')->get();
        
        $this->info("Found {$users->count()} users with NULL karyawan_id");
        
        $synced = 0;
        $notFound = 0;
        
        foreach ($users as $user) {
            // Try to find matching karyawan by email
            $karyawan = MKaryawan::where('email', $user->email)->first();
            
            if ($karyawan) {
                $user->karyawan_id = $karyawan->id;
                $user->save();
                
                $this->line("✅ Synced: {$user->name} ({$user->email}) → karyawan_id: {$karyawan->id}");
                $synced++;
            } else {
                $this->line("❌ Not found: {$user->name} ({$user->email})");
                $notFound++;
            }
        }
        
        $this->newLine();
        $this->info("Sync completed!");
        $this->info("✅ Synced: {$synced}");
        $this->info("❌ Not found: {$notFound}");
        
        return 0;
    }
}
