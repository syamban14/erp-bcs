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
        Schema::table('salary_slips', function (Blueprint $table) {
            // Drop old unique constraint and index that depends on user_id
            $table->dropUnique(['user_id', 'period']);
            
            // Make user_id nullable for Ghost Records
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Add new unique constraint based on NIK
            $table->unique(['employee_nik', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            // Revert changes
            $table->dropUnique(['employee_nik', 'period']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'period']);
        });
    }
};
