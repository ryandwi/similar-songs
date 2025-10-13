<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Optional: Use this for multi-genre support per song
     */
    public function up(): void
    {
        Schema::create('song_genre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('priority')->default(0); // Primary genre = 0
            $table->timestamps();
            
            // Prevent duplicates
            $table->unique(['song_id', 'genre_id']);
            
            // Index for queries
            $table->index(['genre_id', 'song_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('song_genre');
    }
};
