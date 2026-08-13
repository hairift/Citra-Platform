<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('karakter', 50);
            $table->float('best_score')->default(0);
            $table->integer('rank')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'karakter']);
            $table->index(['karakter', 'best_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard');
    }
};
