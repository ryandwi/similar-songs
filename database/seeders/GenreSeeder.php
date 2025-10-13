<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = [
            // Pop & Dance
            ['name' => 'Pop', 'slug' => 'pop', 'description' => 'Popular music with catchy melodies and mainstream appeal'],
            ['name' => 'Dance', 'slug' => 'dance', 'description' => 'Electronic dance music designed for clubs and parties'],
            ['name' => 'EDM', 'slug' => 'edm', 'description' => 'Electronic Dance Music - electronic music produced for nightclubs and festivals'],
            ['name' => 'Electro Pop', 'slug' => 'electro-pop', 'description' => 'Pop music with electronic instrumentation'],
            ['name' => 'Synth Pop', 'slug' => 'synth-pop', 'description' => 'Pop music featuring synthesizers as the dominant musical instrument'],
            
            // Rock & Alternative
            ['name' => 'Rock', 'slug' => 'rock', 'description' => 'Guitar-driven music with strong beats'],
            ['name' => 'Alternative Rock', 'slug' => 'alternative-rock', 'description' => 'Non-mainstream rock music with diverse sounds'],
            ['name' => 'Indie Rock', 'slug' => 'indie-rock', 'description' => 'Independent rock music with DIY aesthetic'],
            ['name' => 'Hard Rock', 'slug' => 'hard-rock', 'description' => 'Heavy and aggressive form of rock music'],
            ['name' => 'Punk Rock', 'slug' => 'punk-rock', 'description' => 'Fast, hard-edged music with anti-establishment lyrics'],
            
            // Hip Hop & R&B
            ['name' => 'Hip Hop', 'slug' => 'hip-hop', 'description' => 'Music featuring rapping and DJing'],
            ['name' => 'Rap', 'slug' => 'rap', 'description' => 'Rhythmic spoken or chanted rhyming lyrics'],
            ['name' => 'R&B', 'slug' => 'r-and-b', 'description' => 'Rhythm and Blues - soulful vocal music'],
            ['name' => 'Soul', 'slug' => 'soul', 'description' => 'Emotional music combining R&B and gospel'],
            ['name' => 'Funk', 'slug' => 'funk', 'description' => 'Groove-oriented music with strong rhythmic bass'],
            
            // Electronic
            ['name' => 'Electronic', 'slug' => 'electronic', 'description' => 'Music produced using electronic instruments and technology'],
            ['name' => 'House', 'slug' => 'house', 'description' => 'Repetitive 4/4 beats and synthesized basslines'],
            ['name' => 'Techno', 'slug' => 'techno', 'description' => 'Repetitive instrumental music with synthetic sounds'],
            ['name' => 'Trance', 'slug' => 'trance', 'description' => 'Electronic music with repeating melodic phrases'],
            ['name' => 'Dubstep', 'slug' => 'dubstep', 'description' => 'Electronic music with heavy bass and syncopated rhythms'],
            
            // Jazz & Blues
            ['name' => 'Jazz', 'slug' => 'jazz', 'description' => 'Improvisational music with swing and blue notes'],
            ['name' => 'Blues', 'slug' => 'blues', 'description' => 'Music expressing sadness with specific chord progressions'],
            ['name' => 'Smooth Jazz', 'slug' => 'smooth-jazz', 'description' => 'Mellow and melodic jazz for easy listening'],
            
            // Country & Folk
            ['name' => 'Country', 'slug' => 'country', 'description' => 'American roots music with guitars and storytelling'],
            ['name' => 'Folk', 'slug' => 'folk', 'description' => 'Traditional acoustic music passed through generations'],
            ['name' => 'Americana', 'slug' => 'americana', 'description' => 'Blend of American roots music styles'],
            
            // Metal
            ['name' => 'Metal', 'slug' => 'metal', 'description' => 'Heavy, loud music with distorted guitars'],
            ['name' => 'Heavy Metal', 'slug' => 'heavy-metal', 'description' => 'Intense rock music with powerful guitars and drums'],
            ['name' => 'Death Metal', 'slug' => 'death-metal', 'description' => 'Extreme metal with growling vocals'],
            
            // Latin & World
            ['name' => 'Latin', 'slug' => 'latin', 'description' => 'Music from Latin America and Spain'],
            ['name' => 'Reggaeton', 'slug' => 'reggaeton', 'description' => 'Latin urban music with dancehall and hip hop influences'],
            ['name' => 'Salsa', 'slug' => 'salsa', 'description' => 'Latin dance music with Cuban and Puerto Rican roots'],
            ['name' => 'Reggae', 'slug' => 'reggae', 'description' => 'Jamaican music with offbeat rhythms'],
            ['name' => 'World', 'slug' => 'world', 'description' => 'Traditional music from various cultures worldwide'],
            
            // Classical & Instrumental
            ['name' => 'Classical', 'slug' => 'classical', 'description' => 'Western art music from various periods'],
            ['name' => 'Instrumental', 'slug' => 'instrumental', 'description' => 'Music without vocals'],
            ['name' => 'Ambient', 'slug' => 'ambient', 'description' => 'Atmospheric music emphasizing tone and mood'],
            
            // K-Pop & Asian
            ['name' => 'K-Pop', 'slug' => 'k-pop', 'description' => 'Korean popular music with catchy hooks'],
            ['name' => 'J-Pop', 'slug' => 'j-pop', 'description' => 'Japanese popular music'],
            ['name' => 'Mandopop', 'slug' => 'mandopop', 'description' => 'Mandarin Chinese popular music'],
            
            // Other Popular Genres
            ['name' => 'Indie', 'slug' => 'indie', 'description' => 'Independent music from various genres'],
            ['name' => 'Alternative', 'slug' => 'alternative', 'description' => 'Non-mainstream music styles'],
            ['name' => 'Gospel', 'slug' => 'gospel', 'description' => 'Christian religious music'],
            ['name' => 'Disco', 'slug' => 'disco', 'description' => 'Dance music popular in the 1970s'],
            ['name' => 'Grunge', 'slug' => 'grunge', 'description' => 'Alternative rock with punk and metal elements'],
            ['name' => 'Trap', 'slug' => 'trap', 'description' => 'Hip hop subgenre with hard-hitting beats'],
            ['name' => 'Lo-Fi', 'slug' => 'lo-fi', 'description' => 'Low-fidelity relaxing music often used for studying'],
            ['name' => 'Ska', 'slug' => 'ska', 'description' => 'Jamaican music with offbeat guitar or piano'],
            ['name' => 'Bluegrass', 'slug' => 'bluegrass', 'description' => 'American roots music with acoustic strings'],
            ['name' => 'Afrobeat', 'slug' => 'afrobeat', 'description' => 'West African music fusing jazz, funk, and traditional rhythms'],
        ];

        foreach ($genres as $genre) {
            DB::table('genres')->insert([
                'name' => $genre['name'],
                'slug' => $genre['slug'],
                'description' => $genre['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✓ Seeded ' . count($genres) . ' genres successfully!');
    }
}
