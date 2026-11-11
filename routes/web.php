<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/submissions/create/{type}', [SubmissionController::class, 'create'])->name('submissions.create');
    Route::resource('submissions', SubmissionController::class)->except(['create', 'destroy']);

    Route::post('/submissions/{submission}/submit', [SubmissionController::class, 'submit'])->name('submissions.submit');
    Route::post('/submissions/{submission}/cancel', [SubmissionController::class, 'cancel'])->name('submissions.cancel');
    Route::post('/submissions/{submission}/approve', [ApprovalController::class, 'approve'])->name('submissions.approve');
    Route::post('/submissions/{submission}/reject', [ApprovalController::class, 'reject'])->name('submissions.reject');

    Route::get('/submissions/{submission}/attachment', [SubmissionController::class, 'attachment'])->name('submissions.attachment');
    Route::get('/submissions/{submission}/letter', [SubmissionController::class, 'letter'])->name('submissions.letter');

    Route::get('/reports/monthly', ReportController::class)->name('reports.monthly');
});
