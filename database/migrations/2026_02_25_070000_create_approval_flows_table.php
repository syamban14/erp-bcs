<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel approval_flows menyimpan riwayat setiap level approval
 * secara polymorphic — bisa dipakai untuk Leave, OvertimeRequest,
 * PermissionRequest, OutstationRequest, dan AttendanceCorrection.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel utama approval_flows
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();

            // Polymorphic: menunjuk ke model yang memiliki approval ini
            $table->morphs('approvable'); // approvable_type + approvable_id

            // Level approval (1=Supervisor, 2=Manager, 3=HR, 4=Director)
            $table->unsignedTinyInteger('level');

            // Status tiap level: pending | approved | rejected
            $table->string('status')->default('pending');

            // Siapa yang melakukan tindakan
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            // Catatan / alasan reject
            $table->text('notes')->nullable();

            $table->timestamps();

            // Index untuk query cepat
            $table->index(['approvable_type', 'approvable_id', 'level']);
        });

        // 2. Tambah current_approval_level ke tabel-tabel yang punya approval
        $tables = ['leaves', 'overtime_requests', 'permission_requests', 'attendance_corrections'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'current_approval_level')) {
                Schema::table($tableName, function (Blueprint $table) {
                    // Level yang sedang menunggu approval (1-4), null = selesai
                    $table->unsignedTinyInteger('current_approval_level')->default(1)->after('status');
                });
            }
        }

        // OutstationRequest sudah punya 2-level approval, tambahkan juga
        if (Schema::hasTable('outstation_requests') && !Schema::hasColumn('outstation_requests', 'current_approval_level')) {
            Schema::table('outstation_requests', function (Blueprint $table) {
                $table->unsignedTinyInteger('current_approval_level')->default(1)->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_flows');

        $tables = ['leaves', 'overtime_requests', 'permission_requests', 'attendance_corrections', 'outstation_requests'];
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'current_approval_level')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('current_approval_level');
                });
            }
        }
    }
};
