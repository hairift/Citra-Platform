<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('karakter', 50); // panji, samba, rumyang, tumenggung, klana
            $table->float('wiraga_score')->default(0);
            $table->float('wirama_score')->default(0);
            $table->float('wirasa_score')->default(0);
            $table->float('total_score')->default(0);
            $table->integer('duration')->default(0); // seconds
            $table->json('feedback')->nullable();
            $table->json('pose_data')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'karakter']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_sessions');
    }
};
