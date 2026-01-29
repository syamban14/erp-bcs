<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
            $table->enum('employment_type', ['regular', 'shift'])->default('regular')->after('role');
        });
        
        // Update existing users who have shift schedules to 'shift'
        // Query shift_schedules from default connection (pgsql)
        $shiftUserIds = DB::connection('pgsql')
            ->table('shift_schedules')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->toArray();
        
        if (!empty($shiftUserIds)) {
            DB::connection('pgsql_master')
                ->table('m_presensi')
                ->whereIn('id', $shiftUserIds)
                ->update(['employment_type' => 'shift']);
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
