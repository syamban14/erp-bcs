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
        if (!Schema::hasColumn('leaves', 'attachment_path')) {
            Schema::table('leaves', function (Blueprint $table) {
                $table->string('attachment_path')->nullable()->after('reason');
            });
        }

        if (!Schema::hasColumn('permission_requests', 'attachment_path')) {
            Schema::table('permission_requests', function (Blueprint $table) {
                $table->string('attachment_path')->nullable()->after('reason');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });

        Schema::table('permission_requests', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};
