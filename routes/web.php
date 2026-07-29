<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

/*
| Fallback for files that still live in storage/app/public
| (legacy layout before public/storage direct writes).
*/
Route::get('/storage/{path}', function (string $path) {
    $path = ltrim(str_replace('\\', '/', $path), '/');

    if (str_contains($path, '..')) {
        abort(404);
    }

    if (Storage::disk('public')->exists($path)) {
        return Storage::disk('public')->response($path);
    }

    $legacy = storage_path('app/public/'.$path);
    if (is_file($legacy)) {
        return response()->file($legacy);
    }

    abort(404);
})->where('path', '.*');
