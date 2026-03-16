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
        Schema::create('user_announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // ID m_presensi / pengguna
            $table->string('announcement_id'); // ID dari Announcements atau string seperti BDAY-123
            $table->timestamp('read_at')->useCurrent();
            $table->timestamps();

            // Mencegah duplicate entry: satu user hanya bisa membaca satu pengumuman sekali secara logis
            $table->unique(['user_id', 'announcement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_announcement_reads');
    }
};
