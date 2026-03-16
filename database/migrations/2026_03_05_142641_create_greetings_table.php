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
        Schema::create('greetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_user_id');
            $table->unsignedBigInteger('target_user_id');
            $table->string('announcement_id');
            $table->integer('year');
            $table->timestamps();

            // Pencegahan SPAM (Satu pengirim hanya bisa mengirim ucapan ke 1 target yang sama per tahun)
            $table->unique(['sender_user_id', 'target_user_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greetings');
    }
};
