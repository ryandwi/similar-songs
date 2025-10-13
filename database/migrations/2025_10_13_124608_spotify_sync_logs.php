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
        Schema::create('spotify_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id', 50)->index();
            $table->enum('entity_type', ['artist', 'song', 'album'])->default('song');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('response_data')->nullable(); // Store API response for debugging
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            // Index for monitoring
            $table->index(['status', 'created_at']);
            $table->index('entity_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spotify_sync_logs');
    }
};
