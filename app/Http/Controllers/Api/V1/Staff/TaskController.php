<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Support\ApiPresenter;
use App\Support\TaskGroups;
use App\Support\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends ApiController
{
    public function index(Request $request, TaskRepository $tasks, SubmissionRepository $submissions): JsonResponse
    {
        $counts = $submissions->countsByTask();
        $items = $tasks->withDetails($tasks->all())
            ->map(function (array $task) use ($counts): array {
                $task['submission_count'] = $counts[(string) ($task['id'] ?? '')] ?? 0;

                return $task;
            });

        $items = $this->search($items, $request, [
            'title', 'description', 'course_name', 'course_code', 'type_label', 'status_label',
        ]);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->filter($items, $request, 'type', 'type');
        $items = $this->filter($items, $request, 'status', 'status');
        $items = $this->filterByTaskGroup($items, $request);
        $items = $this->sort($items, $request, ['deadline', 'title', 'created_at', 'status'], 'deadline');

        return $this->paginated(
            $items,
            $request,
            fn (array $task): array => ApiPresenter::task($task),
        );
    }

    public function show(
        string $id,
        TaskRepository $tasks,
        SubmissionRepository $submissions,
    ): JsonResponse {
        $task = $this->found($tasks->find($id), 'Tugas tidak ditemukan.');
        $task = $tasks->withDetails(collect([$task]))->first();
        $task['submission_count'] = $submissions->countsByTask()[$id] ?? 0;

        return $this->data(ApiPresenter::task($task));
    }

    public function store(
        Request $request,
        TaskRepository $tasks,
        CourseRepository $courses,
        GroupRepository $groups,
    ): JsonResponse {
        $data = $request->validate($this->rules());
        $this->validateTask($data, $courses, $groups);

        $task = $tasks->create($this->prepare($data) + [
            'created_by' => (string) auth('api')->id(),
        ]);

        return response()->json([
            'data' => ApiPresenter::task($tasks->withDetails(collect([$task]))->first()),
            'message' => 'Tugas berhasil ditambahkan.',
        ], 201);
    }

    public function update(
        Request $request,
        string $id,
        TaskRepository $tasks,
        CourseRepository $courses,
        GroupRepository $groups,
    ): JsonResponse {
        $task = $this->found($tasks->find($id), 'Tugas tidak ditemukan.');
        $data = $request->validate($this->rules(true));
        $merged = array_merge($task, $data);
        $this->validateTask($merged, $courses, $groups);

        $updated = $tasks->update($id, $this->prepare($merged));

        return $this->data(ApiPresenter::task(
            $tasks->withDetails(collect([$updated]))->first(),
        ));
    }

    public function destroy(string $id, TaskRepository $tasks): Response
    {
        $this->found($tasks->find($id), 'Tugas tidak ditemukan.');
        $tasks->delete($id);

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
            'type' => [$required, 'string', Rule::in(['individu', 'kelompok'])],
            'meeting_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'status' => [$required, 'string', Rule::in(array_keys(TaskStatus::options()))],
            'title' => [$required, 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'material_url' => ['nullable', 'url:http,https', 'max:500'],
            'collection_link' => ['nullable', 'url:http,https', 'max:500'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['string'],
            'start_at' => ['nullable', 'date'],
            'deadline' => [$required, 'date'],
            'deadline_note' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateTask(array $data, CourseRepository $courses, GroupRepository $groups): void
    {
        $errors = [];
        $course = $courses->find((string) ($data['course_id'] ?? ''));

        if ($course === null) {
            $errors['course_id'] = 'Pilih mata kuliah yang valid.';
        }

        if (($data['type'] ?? 'individu') === 'kelompok') {
            $groupIds = TaskGroups::ids($data['group_ids'] ?? []);

            if ($groupIds === []) {
                $errors['group_ids'] = 'Pilih minimal satu kelompok.';
            } elseif ($course !== null) {
                $allowed = $groups->forCourse((string) $course['id'])
                    ->pluck('id')
                    ->map(fn (mixed $groupId): string => (string) $groupId)
                    ->all();

                if (array_diff($groupIds, $allowed) !== []) {
                    $errors['group_ids'] = 'Kelompok harus berasal dari mata kuliah yang dipilih.';
                }
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
        $type = (string) ($data['type'] ?? 'individu');
        $groupIds = $type === 'kelompok' ? TaskGroups::ids($data['group_ids'] ?? []) : [];

        return [
            'course_id' => trim((string) ($data['course_id'] ?? '')),
            'type' => $type,
            'meeting_number' => $data['meeting_number'] ?? '',
            'status' => TaskStatus::normalize($data['status'] ?? ''),
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'material_url' => trim((string) ($data['material_url'] ?? '')),
            'collection_link' => trim((string) ($data['collection_link'] ?? '')),
            'group_ids' => TaskGroups::csv($groupIds),
            'group_id' => $groupIds[0] ?? '',
            'start_at' => $data['start_at'] ?? '',
            'deadline' => $data['deadline'] ?? '',
            'deadline_note' => trim((string) ($data['deadline_note'] ?? '')),
        ];
    }
}
