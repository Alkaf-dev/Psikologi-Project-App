<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\ApiTokenRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends ApiController
{
    public function index(Request $request, UserRepository $users): JsonResponse
    {
        $items = $this->search($users->all(), $request, [
            'name', 'npm', 'email', 'class_name', 'cohort', 'phone',
        ]);
        $items = $this->filterUsers($items, $request);
        $items = $this->sort($items, $request, ['name', 'npm', 'email', 'class_name', 'cohort', 'created_at'], 'name');

        return $this->paginated(
            $items,
            $request,
            fn (array $user): array => ApiPresenter::user($user),
        );
    }

    public function students(Request $request, UserRepository $users): JsonResponse
    {
        $request->merge(['role' => UserRole::Mahasiswa->value]);

        return $this->index($request, $users);
    }

    public function show(string $id, UserRepository $users): JsonResponse
    {
        return $this->data(ApiPresenter::user(
            $this->found($users->find($id), 'User tidak ditemukan.'),
        ));
    }

    public function store(
        Request $request,
        UserRepository $users,
        StudyProgramRepository $programs,
        GroupMemberRepository $members,
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'npm' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150'],
            'role' => ['required', Rule::in(array_keys(UserRole::options()))],
            'prodi_id' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'cohort' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'active' => ['sometimes', 'boolean'],
            'group_assignments' => ['sometimes', 'array'],
            'group_assignments.*.course_id' => ['required_with:group_assignments', 'string'],
            'group_assignments.*.group_id' => ['nullable', 'string'],
        ]);

        $role = UserRole::tryFromLegacy($data['role']);
        $this->validateUser($users, $programs, $data, $role);
        $assignments = $this->validatedAssignments($data, $role);
        $payload = $this->payload($data, $role, true);

        $user = $users->create($payload);

        if ($assignments !== null) {
            $members->syncAssignmentsForUser((string) $user['id'], $assignments);
        }

        return response()->json([
            'data' => ApiPresenter::user($user),
            'message' => 'User berhasil ditambahkan.',
        ], 201);
    }

    public function update(
        Request $request,
        string $id,
        UserRepository $users,
        StudyProgramRepository $programs,
        GroupMemberRepository $members,
        ApiTokenRepository $tokens,
    ): JsonResponse {
        $user = $this->found($users->find($id), 'User tidak ditemukan.');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'npm' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['sometimes', 'required', 'email', 'max:150'],
            'role' => ['sometimes', 'required', Rule::in(array_keys(UserRole::options()))],
            'prodi_id' => ['sometimes', 'required', 'string', 'max:50'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'class_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'cohort' => ['sometimes', 'nullable', 'string', 'max:10'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'active' => ['sometimes', 'boolean'],
            'group_assignments' => ['sometimes', 'array'],
            'group_assignments.*.course_id' => ['required_with:group_assignments', 'string'],
            'group_assignments.*.group_id' => ['nullable', 'string'],
        ]);

        $merged = array_merge($user, $data);
        $role = UserRole::tryFromLegacy($merged['role'] ?? null);

        $this->validateUser($users, $programs, $merged, $role, $id);
        $this->guardLastActiveAdmin($users, $user, $merged);
        $assignments = $this->validatedAssignments($merged, $role);
        $payload = $this->payload($merged, $role, false);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($payload['password_hash']);
        }

        $updated = $users->update($id, $payload);

        if (array_key_exists('password', $data) && filled($data['password'])) {
            $tokens->revokeAllForUser($id);
        }

        if ($assignments !== null) {
            $members->syncAssignmentsForUser($id, $assignments);
        }

        return $this->data(ApiPresenter::user($updated));
    }

    public function resetPassword(
        Request $request,
        string $id,
        UserRepository $users,
        ApiTokenRepository $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $this->found($users->find($id), 'User tidak ditemukan.');

        $users->update($id, ['password_hash' => Hash::make((string) $data['password'])]);
        $tokens->revokeAllForUser($id);

        return response()->json(['message' => 'Password berhasil direset.']);
    }

    public function destroy(
        string $id,
        UserRepository $users,
        ApiTokenRepository $tokens,
    ): Response {
        $user = $this->found($users->find($id), 'User tidak ditemukan.');

        if ($id === (string) auth('api')->id()) {
            abort(422, 'Tidak dapat menghapus akun sendiri.');
        }

        if ($this->isLastActiveAdmin($users, $user)) {
            abort(422, 'Tidak dapat menghapus admin aktif terakhir.');
        }

        $users->delete($id);
        $tokens->revokeAllForUser($id);

        return response()->noContent();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterUsers(Collection $items, Request $request): Collection
    {
        $role = trim((string) $request->query('role', ''));

        if ($role !== '') {
            $normalized = UserRole::tryFromLegacy($role);

            $items = $items
                ->filter(fn (array $user): bool => UserRole::tryFromLegacy($user['role'] ?? null) === $normalized)
                ->values();
        }

        $items = $this->filter($items, $request, 'prodi_id', 'prodi_id');

        $active = trim((string) $request->query('active', ''));

        if ($active !== '') {
            $expected = filter_var($active, FILTER_VALIDATE_BOOLEAN);

            $items = $items
                ->filter(fn (array $user): bool => $this->active($user) === $expected)
                ->values();
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateUser(
        UserRepository $users,
        StudyProgramRepository $programs,
        array $data,
        ?UserRole $role,
        ?string $exceptId = null,
    ): void {
        $errors = [];

        if ($role === null) {
            $errors['role'] = 'Role tidak valid.';
        }

        if ($programs->find((string) ($data['prodi_id'] ?? '')) === null) {
            $errors['prodi_id'] = 'Pilih Prodi yang valid.';
        }

        if ($role === UserRole::Mahasiswa && blank($data['class_name'] ?? null)) {
            $errors['class_name'] = 'Kelas wajib diisi untuk Mahasiswa.';
        }

        if ($users->emailExists((string) ($data['email'] ?? ''), $exceptId)) {
            $errors['email'] = 'Email sudah digunakan oleh user lain.';
        }

        if ($users->npmExists((string) ($data['npm'] ?? ''), $exceptId)) {
            $errors['npm'] = 'NIM sudah digunakan oleh user lain.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function payload(array $data, UserRole $role, bool $withPassword): array
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'npm' => trim((string) ($data['npm'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'role' => $role->value,
            'prodi_id' => trim((string) ($data['prodi_id'] ?? '')),
            'class_name' => $role === UserRole::Mahasiswa ? trim((string) ($data['class_name'] ?? '')) : '',
            'cohort' => $role === UserRole::Mahasiswa ? trim((string) ($data['cohort'] ?? '')) : '',
            'phone' => trim((string) ($data['phone'] ?? '')),
            'active' => filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($withPassword || filled($data['password'] ?? null)) {
            $payload['password_hash'] = Hash::make((string) $data['password']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, string>>|null
     */
    protected function validatedAssignments(array $data, ?UserRole $role): ?array
    {
        if (! array_key_exists('group_assignments', $data)) {
            return null;
        }

        $assignments = $data['group_assignments'];

        if (! is_array($assignments) || $role !== UserRole::Mahasiswa) {
            return [];
        }

        $courses = app(CourseRepository::class);
        $groups = app(GroupRepository::class);
        $prodiId = (string) ($data['prodi_id'] ?? '');
        $normalized = [];
        $seen = [];
        $errors = [];

        foreach (array_values($assignments) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $courseId = trim((string) ($row['course_id'] ?? ''));
            $groupId = trim((string) ($row['group_id'] ?? ''));

            if ($courseId === '') {
                continue;
            }

            if (isset($seen[$courseId])) {
                $errors["group_assignments.{$index}.course_id"] = 'Mata kuliah tidak boleh dipilih lebih dari sekali.';

                continue;
            }

            $seen[$courseId] = true;
            $course = $courses->find($courseId);

            if ($course === null || ! $courses->isAvailableForProdi($course, $prodiId)) {
                $errors["group_assignments.{$index}.course_id"] = 'Mata kuliah tidak sesuai dengan prodi mahasiswa.';

                continue;
            }

            if ($groupId !== '') {
                $group = $groups->find($groupId);

                if ($group === null || (string) ($group['course_id'] ?? '') !== $courseId) {
                    $errors["group_assignments.{$index}.group_id"] = 'Kelompok tidak sesuai dengan mata kuliah.';

                    continue;
                }
            }

            $normalized[] = ['course_id' => $courseId, 'group_id' => $groupId];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function isLastActiveAdmin(UserRepository $users, array $record): bool
    {
        return $users->isAdmin($record)
            && $users->isActive($record)
            && ! $users->hasOtherActiveAdmin((string) ($record['id'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $data
     */
    protected function guardLastActiveAdmin(UserRepository $users, array $record, array $data): void
    {
        if (! $this->isLastActiveAdmin($users, $record)) {
            return;
        }

        $remainsAdmin = UserRole::tryFromLegacy($data['role'] ?? null)?->isStaff() ?? false;
        $remainsActive = filter_var($data['active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($remainsAdmin && $remainsActive) {
            return;
        }

        throw ValidationException::withMessages([
            $remainsAdmin ? 'active' : 'role' => 'Minimal satu akun admin aktif harus tersedia.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function active(array $user): bool
    {
        return in_array(
            mb_strtolower(trim((string) ($user['active'] ?? ''))),
            ['1', 'true', 'yes', 'ya'],
            true,
        );
    }
}
