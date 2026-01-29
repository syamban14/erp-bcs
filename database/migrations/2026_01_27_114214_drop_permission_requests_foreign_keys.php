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
        Schema::table('permission_requests', function (Blueprint $table) {
            // Drop foreign key constraints
            // Nama constraint bisa berbeda, coba beberapa kemungkinan
            try {
                $table->dropForeign(['user_id']);
            } catch (\Exception $e) {
                // Jika gagal, coba dengan nama constraint eksplisit
                DB::statement('ALTER TABLE permission_requests DROP CONSTRAINT IF EXISTS permission_requests_user_id_foreign');
            }
            
            try {
                $table->dropForeign(['approved_by']);
            } catch (\Exception $e) {
                DB::statement('ALTER TABLE permission_requests DROP CONSTRAINT IF EXISTS permission_requests_approved_by_foreign');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permission_requests', function (Blueprint $table) {
            // Restore foreign keys (rollback)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};
