<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'leaves', 
            'overtime_requests', 
            'permission_requests', 
            'outstation_requests', 
            'attendance_corrections'
        ];

        foreach ($tables as $t) {
            $constraintName = $t . '_user_id_foreign';
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE \"$t\" DROP CONSTRAINT IF EXISTS \"$constraintName\"");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'leaves', 
            'overtime_requests', 
            'permission_requests', 
            'outstation_requests', 
            'attendance_corrections'
        ];

        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }
};
