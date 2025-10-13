<?php
// database/migrations/2025_10_13_000001_create_albums_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id', 50)->unique();
            $table->string('name');
            $table->string('album_type', 50)->nullable();       // album | single | compilation
            $table->string('release_date', 10)->nullable();     // simpan raw (YYYY / YYYY-MM / YYYY-MM-DD)
            $table->string('release_date_precision', 10)->nullable(); // year | month | day
            $table->unsignedSmallInteger('total_tracks')->default(0);
            $table->string('label')->nullable();

            $table->string('image_url')->nullable();            // cover terbesar
            $table->json('images')->nullable();                 // optional: simpan semua resolusi

            $table->string('spotify_url')->nullable();
            $table->string('uri', 100)->nullable();             // spotify:album:...

            $table->json('available_markets')->nullable();      // daftar negara
            $table->timestamps();

            // Index tambahan
            $table->index('name');
            $table->index('release_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
