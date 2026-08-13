<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maestro_references', function (Blueprint $table) {
            $table->id();
            $table->string('karakter', 50);
            $table->string('gerakan_name', 100);
            $table->string('video_path')->nullable();
            $table->json('pose_keyframes')->nullable(); // Golden dataset poses
            $table->string('audio_path')->nullable();
            $table->json('beat_timestamps')->nullable(); // Gamelan beat timings
            $table->text('description')->nullable();
            $table->string('difficulty', 20)->default('mudah');
            $table->timestamps();
            
            $table->index('karakter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maestro_references');
    }
};
