<?php

namespace App\Console\Commands;

use App\Models\MPresensi;
use Illuminate\Console\Command;

class CleanDummyUsers extends Command
{
    protected $signature = 'clean:dummy-users';
    protected $description = 'Delete dummy users with @example.net and @example.org emails';

    public function handle()
    {
        $this->info('Cleaning dummy users...');
        
        // Find dummy users
        $dummyUsers = MPresensi::where(function($query) {
            $query->where('email', 'like', '%@example.net')
                  ->orWhere('email', 'like', '%@example.org')
                  ->orWhere('email', 'like', '%@example.com');
        })
        ->whereNull('karyawan_id')
        ->get();
        
        $this->info("Found {$dummyUsers->count()} dummy users");
        
        if ($dummyUsers->count() === 0) {
            $this->info('No dummy users to delete');
            return 0;
        }
        
        // Show list
        $this->table(
            ['ID', 'Name', 'Email'],
            $dummyUsers->map(fn($u) => [$u->id, $u->name, $u->email])
        );
        
        if ($this->confirm('Do you want to delete these users?', true)) {
            foreach ($dummyUsers as $user) {
                $this->line("Deleting: {$user->name} ({$user->email})");
                $user->delete();
            }
            
            $this->info("✅ Deleted {$dummyUsers->count()} dummy users");
        } else {
            $this->info('Cancelled');
        }
        
        return 0;
    }
}
