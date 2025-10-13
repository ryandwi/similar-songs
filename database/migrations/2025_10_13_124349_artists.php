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
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id', 50)->unique()->index();
            $table->string('name');
            $table->text('image_url')->nullable();
            $table->unsignedInteger('popularity')->default(0); // 0-100
            $table->unsignedInteger('followers')->default(0);
            $table->json('genres')->nullable(); // Array of genre names from Spotify
            $table->string('spotify_url')->nullable();
            $table->timestamps();
            
            // Index for searching
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
