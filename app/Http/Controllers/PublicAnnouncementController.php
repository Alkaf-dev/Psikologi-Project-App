<?php

namespace App\Http\Controllers;

use App\Repositories\AnnouncementRepository;
use Illuminate\Support\Collection;
use Throwable;

class PublicAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = collect();

        try {
            $announcements = app(AnnouncementRepository::class)->withAuthor(
                app(AnnouncementRepository::class)->published('all'),
            );
        } catch (Throwable) {
            //
        }

        return view('public.announcements.index', [
            'announcements' => $announcements instanceof Collection ? $announcements : collect(),
        ]);
    }

    public function show(string $id)
    {
        try {
            $announcement = app(AnnouncementRepository::class)
                ->withAuthor(
                    app(AnnouncementRepository::class)->published('all')->where('id', $id),
                )
                ->first();
        } catch (Throwable) {
            $announcement = null;
        }

        abort_if($announcement === null, 404);

        return view('public.announcements.show', [
            'announcement' => $announcement,
        ]);
    }
}
