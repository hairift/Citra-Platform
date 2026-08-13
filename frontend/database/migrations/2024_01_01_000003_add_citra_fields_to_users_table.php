<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->default('default-avatar.png')->after('password');
            }
            if (!Schema::hasColumn('users', 'level')) {
                $table->string('level')->default('Pemula')->after('avatar');
            }
            if (!Schema::hasColumn('users', 'total_score')) {
                $table->integer('total_score')->default(0)->after('level');
            }
            if (!Schema::hasColumn('users', 'practice_count')) {
                $table->integer('practice_count')->default(0)->after('total_score');
            }
            if (!Schema::hasColumn('users', 'progress')) {
                $table->json('progress')->nullable()->after('practice_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['avatar', 'level', 'total_score', 'practice_count', 'progress'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
