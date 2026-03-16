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
        Schema::connection('pgsql')->create('fatigue_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Changed from employee_id
            $table->timestamp('test_datetime');
            $table->tinyInteger('memory_score')->unsigned()->comment('0-3');
            $table->time('sleep_time');
            $table->integer('reaction_avg_ms')->unsigned();
            $table->json('reaction_times');
            $table->string('fatigue_level', 20)->comment('normal, moderate, severe');
            $table->boolean('is_retry')->default(false);
            $table->timestamp('retry_after')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'test_datetime'], 'idx_user_date'); // Updated index
            $table->index('fatigue_level', 'idx_fatigue_level');
            $table->index('test_datetime', 'idx_test_date');
            
            // Note: Foreign key to m_presensi in master_db cannot be created due to cross-database constraint
            // Referential integrity must be maintained at application level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fatigue_tests');
    }
};
