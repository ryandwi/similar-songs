<?php

use App\Http\Controllers\ArtistController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/similar-artists/{slug}/{spotifyId}', [ArtistController::class, 'show'])
     ->name('artist.show');
