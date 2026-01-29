<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->string('shift_code')->nullable()->after('time_out');
            $table->integer('late_minutes')->default(0)->after('shift_code');
            $table->integer('overtime_minutes')->default(0)->after('late_minutes');
            $table->decimal('working_hours', 5, 2)->nullable()->after('overtime_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropColumn(['shift_code', 'late_minutes', 'overtime_minutes', 'working_hours']);
        });
    }
};
