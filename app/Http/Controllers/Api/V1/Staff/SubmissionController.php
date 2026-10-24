<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\GroupMemberRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\ApiPresenter;
use App\Support\TaskGroups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubmissionController extends ApiController
{
    public function index(Request $request, SubmissionRepository $submissions): JsonResponse
    {
        $items = $submissions->withDetails();
        $items = $this->search($items, $request, ['task_title', 'student_name', 'student_npm', 'group_name']);
        $items = $this->filter($items, $request, 'task_id', 'task_id');
        $items = $this->filter($items, $request, 'user_id', 'user_id');
        $items = $this->filter($items, $request, 'group_id', 'group_id');
        $items = $this->filter($items, $request, 'status', 'status');
        $items = $this->sort($items, $request, ['submitted_at', 'status', 'created_at'], 'submitted_at', 'desc');

        return $this->paginated(
            $items,
            $request,
            fn (array $submission): array => ApiPresenter::submission($submission),
        );
    }

    public function show(string $id, SubmissionRepository $submissions): JsonResponse
    {
        $submission = $this->found($submissions->find($id), 'Pengumpulan tidak ditemukan.');
        $submission = $submissions->withDetails(collect([$submission]))->first();

        return $this->data(ApiPresenter::submission($submission));
    }

    public function store(
        Request $request,
        SubmissionRepository $submissions,
        TaskRepository $tasks,
        UserRepository $users,
        GroupMemberRepository $members,
    ): JsonResponse {
        $data = $request->validate($this->rules());
        $merged = $this->prepare($data);
        $this->validateSubmission($merged, $tasks, $users, $members);

        $task = $tasks->find((string) $merged['task_id']);

        if (($task['type'] ?? 'individu') !== 'kelompok') {
            $merged['group_id'] = '';
        }

        $existing = ($task['type'] ?? 'individu') === 'kelompok'
            ? $submissions->forTaskAndGroup((string) $merged['task_id'], (string) $merged['group_id'])
            : $submissions->forTaskAndUser((string) $merged['task_id'], (string) $merged['user_id']);

        if ($existing !== null && ($task['type'] ?? 'individu') !== 'kelompok') {
            abort(422, 'Mahasiswa ini sudah memiliki pengumpulan untuk tugas tersebut.');
        }

        if (blank($merged['submitted_at'])) {
            $merged['submitted_at'] = now()->toIso8601String();
        }

        if (($task['type'] ?? 'individu') === 'kelompok') {
            $submission = $submissions->upsertForTaskAndGroup(
                (string) $merged['task_id'],
                (string) $merged['group_id'],
                $merged,
            );
        } else {
            $submission = $submissions->create($merged);
        }

        $submission = $submissions->withDetails(collect([$submission]))->first();

        return response()->json([
            'data' => ApiPresenter::submission($submission),
            'message' => 'Pengumpulan berhasil disimpan.',
        ], $existing === null ? 201 : 200);
    }

    public function update(
        Request $request,
        string $id,
        SubmissionRepository $submissions,
        TaskRepository $tasks,
        UserRepository $users,
        GroupMemberRepository $members,
    ): JsonResponse {
        $submission = $this->found($submissions->find($id), 'Pengumpulan tidak ditemukan.');
        $data = $request->validate($this->rules(true));
        $merged = $this->prepare(array_merge($submission, $data));
        $this->validateSubmission($merged, $tasks, $users, $members);

        $task = $tasks->find((string) $merged['task_id']);

        if (($task['type'] ?? 'individu') !== 'kelompok') {
            $merged['group_id'] = '';
        }

        if (blank($merged['submitted_at'])) {
            $merged['submitted_at'] = now()->toIso8601String();
        }

        $updated = $submissions->update($id, $merged);
        $updated = $submissions->withDetails(collect([$updated]))->first();

        return $this->data(ApiPresenter::submission($updated));
    }

    public function destroy(string $id, SubmissionRepository $submissions): Response
    {
        $this->found($submissions->find($id), 'Pengumpulan tidak ditemukan.');
        $submissions->delete($id);

        return response()->noContent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'task_id' => [$required, 'string'],
            'group_id' => ['nullable', 'string'],
            'user_id' => [$required, 'string'],
            'status' => [$required, 'string', Rule::in(['submitted', 'late', 'graded'])],
            'link' => [$required, 'url:http,https', 'max:500'],
            'submitted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'feedback' => ['nullable', 'string'],
            'grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        return [
            'task_id' => trim((string) ($data['task_id'] ?? '')),
            'group_id' => trim((string) ($data['group_id'] ?? '')),
            'user_id' => trim((string) ($data['user_id'] ?? '')),
            'status' => trim((string) ($data['status'] ?? 'submitted')),
            'link' => trim((string) ($data['link'] ?? '')),
            'submitted_at' => $data['submitted_at'] ?? '',
            'notes' => trim((string) ($data['notes'] ?? '')),
            'feedback' => trim((string) ($data['feedback'] ?? '')),
            'grade' => $data['grade'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateSubmission(
        array $data,
        TaskRepository $tasks,
        UserRepository $users,
        GroupMemberRepository $members,
    ): void {
        $errors = [];
        $task = $tasks->find((string) ($data['task_id'] ?? ''));
        $user = $users->find((string) ($data['user_id'] ?? ''));

        if ($task === null) {
            $errors['task_id'] = 'Pilih tugas yang valid.';
        }

        if ($user === null || ! $users->isStudent($user)) {
            $errors['user_id'] = 'Pilih mahasiswa yang valid.';
        }

        if ($task !== null && ($task['type'] ?? 'individu') === 'kelompok') {
            $groupId = (string) ($data['group_id'] ?? '');

            if ($groupId === '') {
                $errors['group_id'] = 'Pilih kelompok untuk tugas kelompok.';
            } else {
                if (! in_array($groupId, TaskGroups::assignedIds($task), true)) {
                    $errors['group_id'] = 'Kelompok tidak ditugaskan pada tugas ini.';
                }

                $memberIds = $members->forGroup($groupId)
                    ->pluck('user_id')
                    ->map(fn (mixed $userId): string => (string) $userId)
                    ->all();

                if (! in_array((string) ($data['user_id'] ?? ''), $memberIds, true)) {
                    $errors['user_id'] = 'Perwakilan harus anggota kelompok yang dipilih.';
                }
            }
        }

        if (($data['status'] ?? '') === 'graded' && ! is_numeric($data['grade'] ?? null)) {
            $errors['grade'] = 'Nilai wajib diisi untuk status dinilai.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
