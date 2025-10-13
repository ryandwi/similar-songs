<?php

// database/migrations/2025_10_13_000010_create_artist_related_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artist_related', function (Blueprint $table) {
            $table->unsignedBigInteger('artist_id');
            $table->unsignedBigInteger('related_artist_id');
            $table->primary(['artist_id','related_artist_id']);

            // Optional FK (aktifkan jika data sudah stabil):
            // $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');
            // $table->foreign('related_artist_id')->references('id')->on('artists')->onDelete('cascade');

            $table->index('related_artist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_related');
    }
};
