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
        Schema::connection('pgsql_master')->create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('m_presensi')->onDelete('cascade');
            $table->string('device_id')->unique(); // One device can only be registered to one user (Anti-Fraud)
            $table->string('device_name')->nullable();
            $table->text('public_key'); // RSA/ECDSA Public Key
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_master')->dropIfExists('user_devices');
    }
};
