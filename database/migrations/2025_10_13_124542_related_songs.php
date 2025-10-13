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
        Schema::create('related_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete(); // Source song
            $table->foreignId('related_song_id')->constrained('songs')->cascadeOnDelete(); // Similar song
            $table->decimal('similarity_score', 5, 4)->default(0); // 0-1, higher = more similar
            $table->string('match_reason')->nullable(); // e.g., "Same BPM & Genre"
            $table->unsignedSmallInteger('rank')->default(0); // 1-100, lower = more similar
            $table->timestamps();
            
            // Prevent duplicates
            $table->unique(['song_id', 'related_song_id']);
            
            // Index for fast lookups
            $table->index(['song_id', 'rank']); // Get top N similar songs
            $table->index('related_song_id'); // Reverse lookup
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('related_songs');
    }
};
