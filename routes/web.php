<?php

use App\Http\Controllers\PublicAnnouncementController;
use App\Http\Controllers\PublicHomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)->name('home');

Route::get('/pengumuman', [PublicAnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/pengumuman/{id}', [PublicAnnouncementController::class, 'show'])->name('announcements.show');

Route::get('/login', fn () => redirect()->route('filament.portal.auth.login'))->name('login');
