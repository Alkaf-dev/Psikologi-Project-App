<?php

use App\Http\Controllers\PublicAnnouncementController;
use App\Http\Controllers\PublicHomeController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)->name('home');

Route::get('/pengumuman', [PublicAnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/pengumuman/{id}', [PublicAnnouncementController::class, 'show'])->name('announcements.show');

$redirectTo = static function (string $target): RedirectResponse {
    $query = request()->getQueryString();

    return redirect()->to($query ? $target.'?'.$query : $target);
};

Route::get('/login', fn (): RedirectResponse => $redirectTo('/app/login'))->name('login');

Route::get('/admin/{path?}', function (?string $path = null) use ($redirectTo): RedirectResponse {
    $path = trim((string) $path, '/');

    if ($path === '') {
        return $redirectTo('/app');
    }

    return $redirectTo('/app/'.$path);
})->where('path', '.*');

$legacyPortalPages = [
    '' => '/app',
    'login' => '/app/login',
    'jadwal-page' => '/app/jadwal-saya',
    'tugas-page' => '/app/tugas-saya',
    'kelompok-page' => '/app/kelompok-saya',
    'pengumuman-page' => '/app/pengumuman-saya',
    'notifikasi-page' => '/app/notifikasi-page',
    'profil-page' => '/app/profil-saya',
];

Route::get('/portal/{path?}', function (?string $path = null) use ($redirectTo, $legacyPortalPages): RedirectResponse {
    $path = trim((string) $path, '/');

    return $redirectTo($legacyPortalPages[$path] ?? '/app');
})->where('path', '.*');
