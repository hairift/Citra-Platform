<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('practice_sessions', 'gerakan')) {
                $table->string('gerakan', 100)->nullable()->after('karakter');
            }
            if (!Schema::hasColumn('practice_sessions', 'maestro_reference_id')) {
                $table->unsignedBigInteger('maestro_reference_id')->nullable()->after('gerakan');
            }
            if (!Schema::hasColumn('practice_sessions', 'grade')) {
                $table->string('grade', 4)->nullable()->after('total_score');
            }
            if (!Schema::hasColumn('practice_sessions', 'frames_analyzed')) {
                $table->unsignedInteger('frames_analyzed')->default(0)->after('duration');
            }
            if (!Schema::hasColumn('practice_sessions', 'correct_frames')) {
                $table->unsignedInteger('correct_frames')->default(0)->after('frames_analyzed');
            }
            if (!Schema::hasColumn('practice_sessions', 'best_streak')) {
                $table->unsignedInteger('best_streak')->default(0)->after('correct_frames');
            }
            if (!Schema::hasColumn('practice_sessions', 'timeline')) {
                // Time-stamped feedback events captured during the session.
                $table->json('timeline')->nullable()->after('feedback');
            }
            if (!Schema::hasColumn('practice_sessions', 'score_series')) {
                // Rolling score samples -> powers the performance chart.
                $table->json('score_series')->nullable()->after('timeline');
            }
            if (!Schema::hasColumn('practice_sessions', 'joint_scores')) {
                // Per-joint accuracy breakdown for the improvement-areas panel.
                $table->json('joint_scores')->nullable()->after('score_series');
            }
            if (!Schema::hasColumn('practice_sessions', 'status')) {
                $table->string('status', 20)->default('completed')->after('joint_scores');
            }
        });

        Schema::table('practice_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('practice_sessions', 'maestro_reference_id')) {
                $table->index('maestro_reference_id', 'practice_sessions_maestro_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('practice_sessions', 'maestro_reference_id')) {
                $table->dropIndex('practice_sessions_maestro_idx');
            }
            foreach ([
                'gerakan', 'maestro_reference_id', 'grade', 'frames_analyzed',
                'correct_frames', 'best_streak', 'timeline', 'score_series',
                'joint_scores', 'status',
            ] as $column) {
                if (Schema::hasColumn('practice_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
