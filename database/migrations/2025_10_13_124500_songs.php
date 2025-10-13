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
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id', 50)->unique()->index();
            $table->string('title');
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->nullable()->constrained()->nullOnDelete();
            $table->string('album_name')->nullable();
            $table->text('album_image_url')->nullable();
            $table->date('release_date')->nullable();
            $table->unsignedInteger('duration_ms'); // Duration in milliseconds
            $table->unsignedInteger('popularity')->default(0); // 0-100
            $table->string('preview_url')->nullable();
            $table->string('spotify_url');
            $table->string('isrc', 20)->nullable()->index(); // International Standard Recording Code
            
            // Audio Features
            $table->decimal('danceability', 5, 4)->default(0); // 0.0000-1.0000
            $table->decimal('energy', 5, 4)->default(0);
            $table->decimal('speechiness', 5, 4)->default(0);
            $table->decimal('acousticness', 5, 4)->default(0);
            $table->decimal('instrumentalness', 5, 4)->default(0);
            $table->decimal('liveness', 5, 4)->default(0);
            $table->decimal('valence', 5, 4)->default(0); // Musical positiveness
            $table->decimal('loudness', 6, 3)->default(0); // Typically -60 to 0 dB
            $table->decimal('tempo', 6, 3)->default(0); // BPM
            $table->unsignedTinyInteger('key')->nullable(); // 0-11 (C, C#, D, etc.)
            $table->unsignedTinyInteger('mode')->nullable(); // 0=minor, 1=major
            $table->unsignedTinyInteger('time_signature')->default(4); // 3-7
            
            // Computed fields
            $table->string('key_signature', 10)->nullable(); // e.g., "C# Major"
            // $table->unsignedSmallInteger('bpm')->storedAs('ROUND(tempo)'); // Virtual column for easier queries
            
            // Metadata
            $table->boolean('explicit')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            // Composite indexes for similarity queries
            $table->index(['genre_id', 'bpm']); // Main similarity query
            $table->index(['genre_id', 'popularity']); // Trending by genre
            $table->index('popularity'); // Global trending
            $table->index('bpm'); // Tempo-based search
            $table->index('energy'); // Energy-based filtering
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
