<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\CourseRepository;
use App\Repositories\ScheduleRepository;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleController extends ApiController
{
    public function index(Request $request, ScheduleRepository $schedules): JsonResponse
    {
        $items = $schedules->withCourse($schedules->all());
        $items = $this->search($items, $request, [
            'course_name', 'course_code', 'course_lecturer', 'day', 'room', 'class_name',
        ]);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->filter($items, $request, 'day', 'day');
        $items = $this->filter($items, $request, 'mode', 'mode');
        $items = $this->filter($items, $request, 'class_name', 'class_name');
        $items = $this->sort($items, $request, ['start_time', 'end_time', 'day', 'class_name'], 'start_time');

        return $this->paginated(
            $items,
            $request,
            fn (array $schedule): array => ApiPresenter::schedule($schedule),
        );
    }

    public function show(string $id, ScheduleRepository $schedules): JsonResponse
    {
        $schedule = $this->found($schedules->find($id), 'Jadwal tidak ditemukan.');

        return $this->data(ApiPresenter::schedule(
            $schedules->withCourse(collect([$schedule]))->first(),
        ));
    }

    public function store(
        Request $request,
        ScheduleRepository $schedules,
        CourseRepository $courses,
    ): JsonResponse {
        $data = $request->validate($this->rules());
        $this->validateSchedule($data, $courses);

        $schedule = $schedules->create($this->prepare($data));

        return response()->json([
            'data' => ApiPresenter::schedule($schedule),
            'message' => 'Jadwal berhasil ditambahkan.',
        ], 201);
    }

    public function update(
        Request $request,
        string $id,
        ScheduleRepository $schedules,
        CourseRepository $courses,
    ): JsonResponse {
        $schedule = $this->found($schedules->find($id), 'Jadwal tidak ditemukan.');

        $data = $request->validate($this->rules(true));
        $merged = array_merge($schedule, $data);
        $this->validateSchedule($merged, $courses);

        $updated = $schedules->update($id, $this->prepare($merged));

        return $this->data(ApiPresenter::schedule($updated));
    }

    public function destroy(string $id, ScheduleRepository $schedules): Response
    {
        $this->found($schedules->find($id), 'Jadwal tidak ditemukan.');
        $schedules->delete($id);

        return response()->noContent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'course_id' => [$required, 'string'],
            'day' => [$required, 'string', Rule::in(ScheduleRepository::days())],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i'],
            'mode' => [$required, 'string', Rule::in(array_keys(ScheduleRepository::modes()))],
            'room' => ['nullable', 'string', 'max:60'],
            'platform' => ['nullable', 'string', 'max:30'],
            'meeting_link' => ['nullable', 'url:http,https', 'max:500'],
            'class_name' => [$required, 'string', 'max:50'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateSchedule(array $data, CourseRepository $courses): void
    {
        $errors = [];
        $courseId = trim((string) ($data['course_id'] ?? ''));

        if ($courses->find($courseId) === null) {
            $errors['course_id'] = 'Pilih mata kuliah yang valid.';
        }

        $mode = ScheduleRepository::normalizeMode($data['mode'] ?? null);

        if ($mode === 'offline' && blank($data['room'] ?? null)) {
            $errors['room'] = 'Ruangan wajib diisi untuk perkuliahan offline.';
        }

        if ($mode === 'online') {
            if (ScheduleRepository::normalizePlatform($data['platform'] ?? null) === '') {
                $errors['platform'] = 'Pilih platform untuk perkuliahan online.';
            }

            if (blank($data['meeting_link'] ?? null)) {
                $errors['meeting_link'] = 'Tautan perkuliahan online wajib diisi.';
            }
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
        $mode = ScheduleRepository::normalizeMode($data['mode'] ?? null);

        return [
            'course_id' => trim((string) ($data['course_id'] ?? '')),
            'day' => trim((string) ($data['day'] ?? '')),
            'start_time' => trim((string) ($data['start_time'] ?? '')),
            'end_time' => trim((string) ($data['end_time'] ?? '')),
            'mode' => $mode,
            'room' => $mode === 'offline' ? trim((string) ($data['room'] ?? '')) : '',
            'platform' => $mode === 'online'
                ? ScheduleRepository::normalizePlatform($data['platform'] ?? null)
                : '',
            'meeting_link' => $mode === 'online' ? trim((string) ($data['meeting_link'] ?? '')) : '',
            'class_name' => trim((string) ($data['class_name'] ?? '')),
        ];
    }
}
