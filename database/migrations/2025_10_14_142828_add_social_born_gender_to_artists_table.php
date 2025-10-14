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
        Schema::table('artists', function (Blueprint $table) {
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('soundcloud_url')->nullable();
            $table->string('myspace_url')->nullable();
            $table->string('bandcamp_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('discogs_url')->nullable();

            $table->string('born_in')->nullable();
            $table->date('born_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('country')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_url',
                'twitter_url',
                'instagram_url',
                'youtube_url',
                'soundcloud_url',
                'myspace_url',
                'bandcamp_url',
                'tiktok_url',
                'discogs_url',
                'born_in',
                'gender',
                'country',
                'born_date'
            ]);
        });
    }
};
