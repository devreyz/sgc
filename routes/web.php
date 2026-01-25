<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/offline', function () {
    return view('offline');
})->name('offline');
