<?php

use App\Http\Controllers\ArtistController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::prefix('similar-artists')->name('artist.')->group(function () {
    Route::get('/', [ArtistController::class, 'index'])->name('index');
    Route::get('/{slug}/{spotifyId}', [ArtistController::class, 'show'])->name('show');
});