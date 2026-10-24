<?php

namespace App\Http\Controllers\Api\V1;

use App\Repositories\AnnouncementRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\StudyProgramRepository;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'name' => 'Psikologi Connect API',
            'version' => 'v1',
            'documentation' => 'docs/openapi.yaml',
            'authentication' => [
                'type' => 'Bearer',
                'login_endpoint' => '/api/v1/auth/login',
            ],
            'resources' => [
                'study_programs' => '/api/v1/study-programs',
                'announcements' => '/api/v1/announcements',
                'settings' => '/api/v1/settings',
                'student' => '/api/v1/student/*',
                'staff' => '/api/v1/staff/*',
            ],
        ]);
    }

    public function programs(Request $request, StudyProgramRepository $programs): JsonResponse
    {
        $items = $this->search(
            $programs->active(),
            $request,
            ['code', 'name', 'degree'],
        );

        return response()->json([
            'data' => $items->map(fn (array $program): array => ApiPresenter::program($program))->values(),
        ]);
    }

    public function announcements(Request $request, AnnouncementRepository $announcements): JsonResponse
    {
        $items = $this->search(
            $announcements->withAuthor($announcements->published('all')),
            $request,
            ['title', 'body', 'author_name', 'course_name'],
        );
        $items = $this->sort($items, $request, ['published_at', 'title'], 'published_at', 'desc');

        return $this->paginated(
            $items,
            $request,
            fn (array $announcement): array => ApiPresenter::announcement($announcement),
        );
    }

    public function announcement(string $id, AnnouncementRepository $announcements): JsonResponse
    {
        $announcement = $announcements->withAuthor(
            $announcements->published('all')->where('id', $id),
        )->first();

        return $this->data(ApiPresenter::announcement(
            $this->found($announcement, 'Pengumuman tidak ditemukan.'),
        ));
    }

    public function settings(SettingsRepository $settings): JsonResponse
    {
        $all = $settings->allKeyed();
        $publicKeys = [
            'program_name',
            'academic_year',
            'semester',
            'contact_email',
            'contact_phone',
            'public_tagline',
            'public_about',
            'portal_note',
        ];

        return response()->json([
            'data' => collect($publicKeys)
                ->mapWithKeys(fn (string $key): array => [$key => $all[$key] ?? ''])
                ->all(),
        ]);
    }
}
