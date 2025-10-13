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
        Schema::create('artist_genre', function (Blueprint $table) {
            $table->unsignedBigInteger('artist_id');
            $table->unsignedBigInteger('genre_id');
            $table->primary(['artist_id', 'genre_id']);
            // Optional FK jika tabel sudah siap:
            // $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');
            // $table->foreign('genre_id')->references('id')->on('genres')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
