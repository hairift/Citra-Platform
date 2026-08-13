<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maestro_references', function (Blueprint $table) {
            if (!Schema::hasColumn('maestro_references', 'slug')) {
                $table->string('slug', 120)->nullable()->after('id');
            }
            if (!Schema::hasColumn('maestro_references', 'gerakan_slug')) {
                $table->string('gerakan_slug', 100)->nullable()->after('gerakan_name');
            }
            if (!Schema::hasColumn('maestro_references', 'role')) {
                // 'maestro' = golden reference, 'latihan' = extra training data
                $table->string('role', 20)->default('maestro')->after('gerakan_slug');
            }
            if (!Schema::hasColumn('maestro_references', 'poster_path')) {
                $table->string('poster_path')->nullable()->after('video_path');
            }
            if (!Schema::hasColumn('maestro_references', 'keyframes_path')) {
                // Relative path to the extracted *_keyframes.json on disk. The
                // JSON itself is far too large for a DB column.
                $table->string('keyframes_path')->nullable()->after('pose_keyframes');
            }
            if (!Schema::hasColumn('maestro_references', 'segments')) {
                $table->json('segments')->nullable()->after('keyframes_path');
            }
            if (!Schema::hasColumn('maestro_references', 'duration_seconds')) {
                $table->float('duration_seconds')->default(0)->after('segments');
            }
            if (!Schema::hasColumn('maestro_references', 'start_time')) {
                $table->float('start_time')->default(0)->after('duration_seconds');
            }
            if (!Schema::hasColumn('maestro_references', 'end_time')) {
                $table->float('end_time')->nullable()->after('start_time');
            }
            if (!Schema::hasColumn('maestro_references', 'frame_count')) {
                $table->unsignedInteger('frame_count')->default(0)->after('end_time');
            }
            if (!Schema::hasColumn('maestro_references', 'detection_rate')) {
                $table->float('detection_rate')->default(0)->after('frame_count');
            }
            if (!Schema::hasColumn('maestro_references', 'sample_frames')) {
                // Annotated stills published under public/dataset/<karakter>/frames
                $table->json('sample_frames')->nullable()->after('detection_rate');
            }
            if (!Schema::hasColumn('maestro_references', 'hitungan')) {
                $table->unsignedSmallInteger('hitungan')->default(8)->after('difficulty');
            }
            if (!Schema::hasColumn('maestro_references', 'tips')) {
                $table->json('tips')->nullable()->after('hitungan');
            }
            if (!Schema::hasColumn('maestro_references', 'instructions')) {
                $table->json('instructions')->nullable()->after('tips');
            }
            if (!Schema::hasColumn('maestro_references', 'order_index')) {
                $table->unsignedSmallInteger('order_index')->default(0)->after('instructions');
            }
            if (!Schema::hasColumn('maestro_references', 'is_published')) {
                $table->boolean('is_published')->default(true)->after('order_index');
            }
        });

        Schema::table('maestro_references', function (Blueprint $table) {
            $table->index(['karakter', 'order_index'], 'maestro_karakter_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('maestro_references', function (Blueprint $table) {
            $table->dropIndex('maestro_karakter_order_idx');
            foreach ([
                'slug', 'gerakan_slug', 'role', 'poster_path', 'keyframes_path',
                'segments', 'duration_seconds', 'start_time', 'end_time',
                'frame_count', 'detection_rate', 'sample_frames', 'hitungan',
                'tips', 'instructions', 'order_index', 'is_published',
            ] as $column) {
                if (Schema::hasColumn('maestro_references', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
