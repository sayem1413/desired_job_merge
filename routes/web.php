<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesiredJobTableController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/desired-job-merge', [DesiredJobTableController::class, 'index']);
Route::get('/desired-job-merge2', [DesiredJobTableController::class, 'index2']);