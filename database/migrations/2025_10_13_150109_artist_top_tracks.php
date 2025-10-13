<?php
// database/migrations/2025_10_13_000020_create_artist_top_tracks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artist_top_tracks', function (Blueprint $table) {
            $table->unsignedBigInteger('artist_id');
            $table->unsignedBigInteger('song_id');
            $table->unsignedTinyInteger('rank')->default(0); // Ranking 1-10
            $table->string('market', 2)->default('US'); // Market country code
            $table->timestamp('synced_at')->nullable(); // Kapan terakhir sync
            
            $table->primary(['artist_id', 'song_id', 'market']);
            
            // Optional FK (aktifkan setelah data stabil)
            // $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');
            // $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade');
            
            $table->index(['artist_id', 'market']);
            $table->index('rank');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_top_tracks');
    }
};
