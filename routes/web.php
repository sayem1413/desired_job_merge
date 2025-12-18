<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesiredJobTableOldController;
use App\Http\Controllers\DesiredJobTableController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/desired-job-merge', [DesiredJobTableController::class, 'index']);

Route::get('/desired-jobs/analysis/pdf', [DesiredJobTableController::class, 'downloadPdfReport']);

// Route::get('/desired-job-merge-old', [DesiredJobTableOldController::class, 'index']);
// Route::get('/desired-job-merge1-old', [DesiredJobTableOldController::class, 'index1']);
// Route::get('/desired-job-merge2-old', [DesiredJobTableOldController::class, 'index2']);