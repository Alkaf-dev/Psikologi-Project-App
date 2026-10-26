<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\UserRepository;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class GroupController extends ApiController
{
    public function index(Request $request, GroupRepository $groups): JsonResponse
    {
        $items = $groups->withDetails($groups->all());
        $items = $this->search($items, $request, ['name', 'description', 'course_name', 'leader_name']);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->sort($items, $request, ['name', 'course_name', 'created_at'], 'name');

        $includeMembers = $request->boolean('with_members');

        return $this->paginated(
            $items,
            $request,
            fn (array $group): array => ApiPresenter::group(
                $group,
                $includeMembers ? $groups->membersWithUser((string) $group['id']) : null,
                $includeMembers,
            ),
        );
    }

    public function show(string $id, GroupRepository $groups): JsonResponse
    {
        $group = $this->found($groups->find($id), 'Kelompok tidak ditemukan.');
        $group = $groups->withDetails(collect([$group]))->first();

        return $this->data(ApiPresenter::group(
            $group,
            $groups->membersWithUser($id),
            true,
        ));
    }

    public function store(
        Request $request,
        GroupRepository $groups,
        GroupMemberRepository $members,
        UserRepository $users,
        CourseRepository $courses,
    ): JsonResponse {
        $data = $request->validate([
            'course_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:80'],
            'leader_id' => ['required', 'string'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['string'],
            'description' => ['nullable', 'string'],
        ]);

        $course = $this->found($courses->find((string) $data['course_id']), 'Mata kuliah tidak ditemukan.');
        $memberIds = collect($this->memberIds($data))
            ->push((string) $data['leader_id'])
            ->unique()
            ->values()
            ->all();
        $this->validateMembers(
            $course,
            (string) $data['leader_id'],
            $memberIds,
            $users,
            $courses,
            $members,
            null,
        );

        $group = $groups->create([
            'course_id' => (string) $course['id'],
            'name' => trim((string) $data['name']),
            'leader_id' => (string) $data['leader_id'],
            'description' => trim((string) ($data['description'] ?? '')),
        ]);

        $members->syncForGroup((string) $group['id'], $memberIds, (string) $group['leader_id']);
        $group = $groups->withDetails(collect([$group]))->first();

        return response()->json([
            'data' => ApiPresenter::group($group, $groups->membersWithUser((string) $group['id']), true),
            'message' => 'Kelompok berhasil ditambahkan.',
        ], 201);
    }

    public function update(
        Request $request,
        string $id,
        GroupRepository $groups,
        GroupMemberRepository $members,
        UserRepository $users,
        CourseRepository $courses,
    ): JsonResponse {
        $group = $this->found($groups->find($id), 'Kelompok tidak ditemukan.');

        $data = $request->validate([
            'course_id' => ['sometimes', 'required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'leader_id' => ['sometimes', 'required', 'string'],
            'member_ids' => ['sometimes', 'nullable', 'array'],
            'member_ids.*' => ['string'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $merged = array_merge($group, $data);
        $course = $this->found($courses->find((string) $merged['course_id']), 'Mata kuliah tidak ditemukan.');
        $memberIds = array_key_exists('member_ids', $data)
            ? $this->memberIds($data)
            : $members->forGroup($id)->pluck('user_id')->map(fn (mixed $userId): string => (string) $userId)->all();
        $memberIds = collect($memberIds)
            ->push((string) $merged['leader_id'])
            ->unique()
            ->values()
            ->all();

        $this->validateMembers(
            $course,
            (string) $merged['leader_id'],
            $memberIds,
            $users,
            $courses,
            $members,
            $id,
        );

        $updated = $groups->update($id, [
            'course_id' => (string) $course['id'],
            'name' => trim((string) $merged['name']),
            'leader_id' => (string) $merged['leader_id'],
            'description' => trim((string) ($merged['description'] ?? '')),
        ]);

        $members->syncForGroup($id, $memberIds, (string) $updated['leader_id']);
        $updated = $groups->withDetails(collect([$updated]))->first();

        return $this->data(ApiPresenter::group(
            $updated,
            $groups->membersWithUser($id),
            true,
        ));
    }

    public function destroy(
        string $id,
        GroupRepository $groups,
        GroupMemberRepository $members,
    ): Response {
        $this->found($groups->find($id), 'Kelompok tidak ditemukan.');

        foreach ($members->forGroup($id) as $member) {
            $members->delete((string) $member['id']);
        }

        $groups->delete($id);

        return response()->noContent();
    }

    public function addMembers(
        Request $request,
        string $id,
        GroupRepository $groups,
        GroupMemberRepository $members,
        UserRepository $users,
        CourseRepository $courses,
    ): JsonResponse {
        $group = $this->found($groups->find($id), 'Kelompok tidak ditemukan.');
        $course = $this->found($courses->find((string) $group['course_id']), 'Mata kuliah tidak ditemukan.');

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'string'],
        ]);

        $existing = $members->forGroup($id)
            ->pluck('user_id')
            ->map(fn (mixed $userId): string => (string) $userId)
            ->all();
        $newIds = collect($data['user_ids'])
            ->map(fn (mixed $userId): string => trim((string) $userId))
            ->filter()
            ->unique()
            ->reject(fn (string $userId): bool => in_array($userId, $existing, true))
            ->values()
            ->all();

        $this->validateMembers(
            $course,
            (string) $group['leader_id'],
            array_merge($existing, $newIds),
            $users,
            $courses,
            $members,
            $id,
        );

        foreach ($newIds as $userId) {
            $members->addMember($id, $userId);
        }

        return response()->json([
            'data' => ApiPresenter::group(
                $groups->withDetails(collect([$group]))->first(),
                $groups->membersWithUser($id),
                true,
            ),
            'message' => count($newIds).' anggota ditambahkan.',
        ]);
    }

    public function removeMember(
        string $id,
        string $userId,
        GroupRepository $groups,
        GroupMemberRepository $members,
    ): Response {
        $group = $this->found($groups->find($id), 'Kelompok tidak ditemukan.');

        if ((string) ($group['leader_id'] ?? '') === $userId) {
            abort(422, 'Ketua kelompok tidak dapat dikeluarkan. Ubah ketua terlebih dahulu.');
        }

        $member = $members->forGroup($id)->first(
            fn (array $member): bool => (string) ($member['user_id'] ?? '') === $userId,
        );

        $this->found($member, 'Anggota tidak ditemukan pada kelompok ini.');
        $members->delete((string) $member['id']);

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    protected function memberIds(array $data): array
    {
        return collect($data['member_ids'] ?? [])
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<int, string>  $memberIds
     */
    protected function validateMembers(
        array $course,
        string $leaderId,
        array $memberIds,
        UserRepository $users,
        CourseRepository $courses,
        GroupMemberRepository $members,
        ?string $exceptGroupId,
    ): void {
        $errors = [];
        $leader = $users->find($leaderId);

        if ($leader === null || ! $users->isStudent($leader) || ! $users->isActive($leader)) {
            $errors['leader_id'] = 'Ketua harus mahasiswa aktif yang valid.';
        } elseif (! $courses->isAvailableForProdi($course, (string) ($leader['prodi_id'] ?? ''))) {
            $errors['leader_id'] = 'Prodi ketua tidak sesuai dengan mata kuliah.';
        }

        $memberIds = collect($memberIds)->push($leaderId)->unique()->values()->all();

        foreach ($memberIds as $userId) {
            $user = $users->find($userId);

            if ($user === null || ! $users->isStudent($user) || ! $users->isActive($user)) {
                $errors['member_ids'] = 'Semua anggota harus mahasiswa aktif yang valid.';

                continue;
            }

            if (! $courses->isAvailableForProdi($course, (string) ($user['prodi_id'] ?? ''))) {
                $errors['member_ids'] = 'Terdapat anggota yang Produnya tidak sesuai dengan mata kuliah.';

                continue;
            }

            $membership = $members->membershipForCourse($userId, (string) $course['id']);

            if ($membership !== null && (string) ($membership['group_id'] ?? '') !== (string) $exceptGroupId) {
                $errors['member_ids'] = 'Terdapat mahasiswa yang sudah memiliki kelompok pada mata kuliah ini.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
