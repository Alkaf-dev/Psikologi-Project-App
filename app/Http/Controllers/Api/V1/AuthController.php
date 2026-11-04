<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Repositories\ApiTokenRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use App\Sheets\SpreadsheetGateway;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(
        Request $request,
        UserRepository $users,
        StudyProgramRepository $programs,
        ApiTokenRepository $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
            'prodi_id' => ['required', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $program = $programs->find((string) $data['prodi_id']);

        if ($program === null || ! $programs->isActive($program)) {
            throw ValidationException::withMessages([
                'prodi_id' => 'Pilih program studi yang aktif.',
            ]);
        }

        $user = $users->findActiveByLoginAndProdi((string) $data['login'], (string) $data['prodi_id']);
        $passwordHash = (string) ($user['password_hash'] ?? '');

        if ($user === null || $passwordHash === '' || ! Hash::check((string) $data['password'], $passwordHash)) {
            throw ValidationException::withMessages([
                'login' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
            ]);
        }

        $role = UserRole::tryFromLegacy($user['role'] ?? null);

        if ($role === null) {
            throw ValidationException::withMessages([
                'login' => 'Akun ini belum memiliki role yang valid.',
            ]);
        }

        $issued = $tokens->issue(
            (string) $user['id'],
            (string) ($data['device_name'] ?? 'API Client'),
            isset($data['expires_in_days']) ? (int) $data['expires_in_days'] : null,
        );

        $token = $issued['token'];

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issued['plain_text'],
            'expires_at' => $token['expires_at'] ?? null,
            'token' => ApiPresenter::token($token),
            'user' => ApiPresenter::user($user),
        ]);
    }

    public function logout(Request $request, ApiTokenRepository $tokens): Response
    {
        $token = $this->currentToken($request);

        if ($token !== null) {
            $tokens->revoke((string) $token['id']);
        }

        return response()->noContent();
    }

    public function me(): JsonResponse
    {
        return $this->data(ApiPresenter::user($this->currentUser()));
    }

    public function updateProfile(Request $request, UserRepository $users): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $this->currentUser();

        $updated = $users->update((string) $user['id'], [
            'name' => trim((string) $data['name']),
            'phone' => trim((string) ($data['phone'] ?? '')),
        ]);

        return $this->data(ApiPresenter::user($updated));
    }

    public function uploadAvatar(Request $request, UserRepository $users, SpreadsheetGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $this->ensureUserMediaColumns($gateway);

        $user = $this->currentUser();
        $file = $data['avatar'];
        $path = $file->storeAs(
            'avatars',
            Str::ulid().'.'.mb_strtolower($file->extension() ?: 'jpg'),
            'uploads',
        );

        if ($path === false) {
            abort(500, 'Gagal menyimpan foto profil.');
        }

        $this->deleteUploadedMedia($user['avatar_url'] ?? null);

        $updated = $users->update((string) $user['id'], [
            'avatar_url' => $this->uploadedMediaUrl($request, $path),
        ]);

        return $this->data(ApiPresenter::user($updated));
    }

    public function deleteAvatar(UserRepository $users, SpreadsheetGateway $gateway): JsonResponse
    {
        $this->ensureUserMediaColumns($gateway);

        $user = $this->currentUser();

        $this->deleteUploadedMedia($user['avatar_url'] ?? null);

        $updated = $users->update((string) $user['id'], ['avatar_url' => '']);

        return $this->data(ApiPresenter::user($updated));
    }

    public function uploadCover(Request $request, UserRepository $users, SpreadsheetGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'cover' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $this->ensureUserMediaColumns($gateway);

        $user = $this->currentUser();
        $file = $data['cover'];
        $path = $file->storeAs(
            'covers',
            Str::ulid().'.'.mb_strtolower($file->extension() ?: 'jpg'),
            'uploads',
        );

        if ($path === false) {
            abort(500, 'Gagal menyimpan foto sampul.');
        }

        $this->deleteUploadedMedia($user['cover_url'] ?? null);

        $updated = $users->update((string) $user['id'], [
            'cover_url' => $this->uploadedMediaUrl($request, $path),
        ]);

        return $this->data(ApiPresenter::user($updated));
    }

    public function setCoverPreset(Request $request, UserRepository $users, SpreadsheetGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'cover_url' => ['required', 'string', 'max:2048', 'url'],
        ]);

        $url = trim((string) $data['cover_url']);
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'cover_url' => 'URL sampul tidak valid.',
            ]);
        }

        $this->ensureUserMediaColumns($gateway);

        $user = $this->currentUser();

        $this->deleteUploadedMedia($user['cover_url'] ?? null);

        $updated = $users->update((string) $user['id'], ['cover_url' => $url]);

        return $this->data(ApiPresenter::user($updated));
    }

    public function deleteCover(UserRepository $users, SpreadsheetGateway $gateway): JsonResponse
    {
        $this->ensureUserMediaColumns($gateway);

        $user = $this->currentUser();

        $this->deleteUploadedMedia($user['cover_url'] ?? null);

        $updated = $users->update((string) $user['id'], ['cover_url' => '']);

        return $this->data(ApiPresenter::user($updated));
    }

    public function updatePassword(Request $request, UserRepository $users, ApiTokenRepository $tokens): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->currentUser();

        if (! Hash::check((string) $data['current_password'], (string) ($user['password_hash'] ?? ''))) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        $users->update((string) $user['id'], [
            'password_hash' => Hash::make((string) $data['password']),
        ]);

        $current = $this->currentToken($request);
        $tokens->revokeAllForUser((string) $user['id'], $current['id'] ?? null);

        return response()->json(['message' => 'Password berhasil diperbarui.']);
    }

    public function tokens(ApiTokenRepository $tokens): JsonResponse
    {
        $user = $this->currentUser();
        $current = $this->currentToken(request());

        $items = $tokens->activeForUser((string) $user['id'])
            ->map(function (array $token) use ($current): array {
                $item = ApiPresenter::token($token);
                $item['current'] = (string) ($token['id'] ?? '') === (string) ($current['id'] ?? '');

                return $item;
            })
            ->values();

        return response()->json(['data' => $items]);
    }

    public function revokeToken(Request $request, string $id, ApiTokenRepository $tokens): Response
    {
        $user = $this->currentUser();
        $token = $tokens->find($id);

        if ($token === null || (string) ($token['user_id'] ?? '') !== (string) $user['id']) {
            abort(404, 'Token tidak ditemukan.');
        }

        $current = $this->currentToken($request);

        if ((string) ($current['id'] ?? '') === $id) {
            abort(422, 'Gunakan endpoint logout untuk mencabut token yang sedang dipakai.');
        }

        if (! $tokens->revoke($id)) {
            abort(422, 'Token sudah tidak aktif.');
        }

        return response()->noContent();
    }

    private function ensureUserMediaColumns(SpreadsheetGateway $gateway): void
    {
        $gateway->ensureSheet('users', (array) config('sheets.tabs.users', []));
    }

    private function uploadedMediaUrl(Request $request, string $path): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/').'/uploads/'.ltrim($path, '/');
    }

    private function deleteUploadedMedia(?string $url): void
    {
        $path = (string) parse_url((string) $url, PHP_URL_PATH);
        $marker = '/uploads/';
        $position = strpos($path, $marker);

        if ($position === false) {
            return;
        }

        $relative = ltrim(substr($path, $position + strlen($marker)), '/');

        if ($relative !== '' && Storage::disk('uploads')->exists($relative)) {
            Storage::disk('uploads')->delete($relative);
        }
    }
}
