<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'current_streak')) {
                $table->unsignedInteger('current_streak')->default(0)->after('practice_count');
            }
            if (!Schema::hasColumn('users', 'longest_streak')) {
                $table->unsignedInteger('longest_streak')->default(0)->after('current_streak');
            }
            if (!Schema::hasColumn('users', 'total_practice_seconds')) {
                $table->unsignedBigInteger('total_practice_seconds')->default(0)->after('longest_streak');
            }
            if (!Schema::hasColumn('users', 'last_practice_at')) {
                $table->timestamp('last_practice_at')->nullable()->after('total_practice_seconds');
            }
            if (!Schema::hasColumn('users', 'is_admin')) {
                // Gates maestro-video uploads and dataset management.
                $table->boolean('is_admin')->default(false)->after('last_practice_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'current_streak', 'longest_streak', 'total_practice_seconds',
                'last_practice_at', 'is_admin',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
