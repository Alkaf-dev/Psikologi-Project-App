<?php

namespace App\Http\Controllers;

use App\Repositories\AnnouncementRepository;
use App\Repositories\SettingsRepository;
use Illuminate\Support\Collection;
use Throwable;

class PublicHomeController extends Controller
{
    public function __invoke()
    {
        $settings = [];
        $announcements = collect();

        try {
            $settings = app(SettingsRepository::class)->allKeyed();
            $announcements = app(AnnouncementRepository::class)->withAuthor(
                app(AnnouncementRepository::class)->published('all')->take(3),
            );
        } catch (Throwable) {
            //
        }

        return view('public.home', [
            'settings' => $settings,
            'announcements' => $announcements instanceof Collection ? $announcements : collect(),
        ]);
    }
}
