<?php

use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return 'Hello World!';
});

Route::prefix('api')->group(function () {
    // Dashboard Routes...
    Route::get('/stats', function () {
        return response()->json([
            'message' => 'success',
        ]);
    });
});

// Catch-all Route...
Route::get('/{view?}', 'HomeController@index')->where('view', '(.*)')->name('laravel-package.index');
