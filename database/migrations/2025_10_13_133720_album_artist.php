<?php

// database/migrations/2025_10_13_000002_create_album_artist_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('album_artist', function (Blueprint $table) {
            $table->unsignedBigInteger('album_id');
            $table->unsignedBigInteger('artist_id');
            $table->primary(['album_id', 'artist_id']);

            // Optional FK (aktifkan jika sudah ada data stabil)
            // $table->foreign('album_id')->references('id')->on('albums')->onDelete('cascade');
            // $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');

            $table->index('artist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_artist');
    }
};

