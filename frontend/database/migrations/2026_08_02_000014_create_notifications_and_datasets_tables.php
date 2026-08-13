<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // In-app notifications (achievement unlocked, rank change, reminders).
        Schema::create('citra_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 40);           // achievement | leaderboard | reminder | system
            $table->string('title', 150);
            $table->string('message', 500)->nullable();
            $table->string('icon', 16)->default('🔔');
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        // Registry of every pose dataset produced by backend/build_dataset.py.
        Schema::create('pose_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('karakter', 50);
            $table->string('title', 200);
            $table->string('role', 20)->default('maestro');
            $table->string('source_video', 255)->nullable();
            $table->string('web_video', 255)->nullable();
            $table->string('poster', 255)->nullable();
            $table->float('duration_seconds')->default(0);
            $table->float('sample_fps')->default(6);
            $table->unsignedInteger('sampled_frames')->default(0);
            $table->unsignedInteger('detected_frames')->default(0);
            $table->float('detection_rate')->default(0);
            $table->unsignedSmallInteger('segment_count')->default(0);
            $table->string('resolution', 20)->nullable();
            $table->json('segments')->nullable();
            $table->json('frames')->nullable();       // published annotated stills
            $table->text('description')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->timestamps();

            $table->index('karakter');
        });

        // Per-gerakan progress so the tutorial can show real completion state.
        Schema::create('gerakan_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('karakter', 50);
            $table->string('gerakan', 100);
            $table->float('best_score')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'karakter', 'gerakan'], 'gerakan_progress_unique');
            $table->index(['user_id', 'karakter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gerakan_progress');
        Schema::dropIfExists('pose_datasets');
        Schema::dropIfExists('citra_notifications');
    }
};
