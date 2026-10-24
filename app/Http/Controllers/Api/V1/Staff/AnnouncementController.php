<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Support\ApiPresenter;
use App\Support\ProdiAssignments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends ApiController
{
    public function index(Request $request, AnnouncementRepository $announcements): JsonResponse
    {
        $items = $announcements->withAuthor($announcements->all());
        $items = $this->search($items, $request, ['title', 'body', 'author_name', 'course_name', 'prodi_names']);
        $items = $this->filter($items, $request, 'audience', 'audience');
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->filterProdi($items, $request);
        $items = $this->sort($items, $request, ['published_at', 'title', 'created_at'], 'published_at', 'desc');

        return $this->paginated(
            $items,
            $request,
            fn (array $announcement): array => ApiPresenter::announcement($announcement),
        );
    }

    public function show(string $id, AnnouncementRepository $announcements): JsonResponse
    {
        $announcement = $this->found($announcements->find($id), 'Pengumuman tidak ditemukan.');

        return $this->data(ApiPresenter::announcement(
            $announcements->withAuthor(collect([$announcement]))->first(),
        ));
    }

    public function store(
        Request $request,
        AnnouncementRepository $announcements,
        CourseRepository $courses,
    ): JsonResponse {
        $data = $request->validate($this->rules());
        $this->validateAnnouncement($data, $courses);

        $data['created_by'] = (string) auth('api')->id();
        $announcement = $announcements->create($this->prepare($data));

        return response()->json([
            'data' => ApiPresenter::announcement(
                $announcements->withAuthor(collect([$announcement]))->first(),
            ),
            'message' => 'Pengumuman berhasil ditambahkan.',
        ], 201);
    }

    public function update(
        Request $request,
        string $id,
        AnnouncementRepository $announcements,
        CourseRepository $courses,
    ): JsonResponse {
        $announcement = $this->found($announcements->find($id), 'Pengumuman tidak ditemukan.');
        $data = $request->validate($this->rules(true));
        $merged = array_merge($announcement, $data);
        $this->validateAnnouncement($merged, $courses);

        $updated = $announcements->update($id, $this->prepare($merged));

        return $this->data(ApiPresenter::announcement(
            $announcements->withAuthor(collect([$updated]))->first(),
        ));
    }

    public function destroy(string $id, AnnouncementRepository $announcements): Response
    {
        $this->found($announcements->find($id), 'Pengumuman tidak ditemukan.');
        $announcements->delete($id);

        return response()->noContent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:180'],
            'audience' => [$required, 'string', Rule::in(['all', 'mahasiswa', 'admin'])],
            'course_id' => ['sometimes', 'nullable', 'string'],
            'prodi_ids' => ['sometimes', 'nullable', 'array'],
            'prodi_ids.*' => ['string'],
            'published_at' => [$required, 'date'],
            'body' => [$required, 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateAnnouncement(array $data, CourseRepository $courses): void
    {
        $errors = [];
        $courseId = trim((string) ($data['course_id'] ?? ''));

        if ($courseId !== '' && $courses->find($courseId) === null) {
            $errors['course_id'] = 'Mata kuliah tidak valid.';
        }

        if (($data['audience'] ?? 'all') === 'mahasiswa'
            && ProdiAssignments::invalidIds($data['prodi_ids'] ?? []) !== []) {
            $errors['prodi_ids'] = 'Pilihan Prodi tidak valid.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        $audience = (string) ($data['audience'] ?? 'all');

        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'audience' => $audience,
            'course_id' => trim((string) ($data['course_id'] ?? '')),
            'prodi_ids' => $audience === 'mahasiswa' ? ProdiAssignments::csv($data['prodi_ids'] ?? []) : '',
            'published_at' => $data['published_at'] ?? '',
            'body' => trim((string) ($data['body'] ?? '')),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterProdi(Collection $items, Request $request): Collection
    {
        $prodiId = trim((string) $request->query('prodi_id', ''));

        if ($prodiId === '') {
            return $items;
        }

        return $items
            ->filter(fn (array $announcement): bool => ($announcement['audience'] ?? 'all') === 'mahasiswa'
                && ProdiAssignments::includes($announcement['prodi_ids'] ?? '', $prodiId, true))
            ->values();
    }
}
