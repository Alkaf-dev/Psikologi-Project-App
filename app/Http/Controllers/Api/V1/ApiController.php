<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class ApiController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    protected function currentUser(): array
    {
        $user = auth('api')->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $record = app(UserRepository::class)->find((string) $user->getAuthIdentifier());

        if ($record === null) {
            abort(401, 'Akun tidak ditemukan atau tidak aktif.');
        }

        return $record;
    }

    protected function currentToken(Request $request): ?array
    {
        $token = $request->attributes->get('api_token');

        return is_array($token) ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function data(array $data): JsonResponse
    {
        return response()->json(['data' => $data]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $presenter
     */
    protected function paginated(Collection $items, Request $request, ?callable $presenter = null): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', (int) config('api.default_per_page', 25));
        $perPage = max(1, min((int) config('api.max_per_page', 100), $perPage));
        $page = max(1, (int) $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
        );

        $data = collect($paginator->items());

        if ($presenter !== null) {
            $data = $data->map($presenter)->filter()->values();
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $request->fullUrlWithQuery(['page' => 1]),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
                'last' => $paginator->lastPage() > 0
                    ? $request->fullUrlWithQuery(['page' => $paginator->lastPage()])
                    : null,
            ],
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<int, string>  $fields
     * @return Collection<int, array<string, mixed>>
     */
    protected function search(Collection $items, Request $request, array $fields): Collection
    {
        $query = mb_strtolower(trim((string) $request->query('search', '')));

        if ($query === '') {
            return $items;
        }

        return $items
            ->filter(function (array $item) use ($fields, $query): bool {
                foreach ($fields as $field) {
                    $value = $item[$field] ?? '';

                    if (! is_scalar($value) && $value !== null) {
                        continue;
                    }

                    if (str_contains(mb_strtolower((string) $value), $query)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<int, string>  $allowed
     * @return Collection<int, array<string, mixed>>
     */
    protected function sort(Collection $items, Request $request, array $allowed, string $default, string $direction = 'asc'): Collection
    {
        $sort = (string) $request->query('sort', $default);
        $sort = in_array($sort, $allowed, true) ? $sort : $default;
        $order = mb_strtolower((string) $request->query('direction', $direction)) === 'desc' ? 'desc' : 'asc';

        $sorted = $items->sortBy(
            fn (array $item): string => mb_strtolower((string) ($item[$sort] ?? '')),
            SORT_NATURAL | SORT_FLAG_CASE,
        )->values();

        return $order === 'desc' ? $sorted->reverse()->values() : $sorted;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function filter(Collection $items, Request $request, string $key, string $field): Collection
    {
        $value = trim((string) $request->query($key, ''));

        if ($value === '') {
            return $items;
        }

        return $items
            ->filter(fn (array $item): bool => (string) ($item[$field] ?? '') === $value)
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $record
     * @return array<string, mixed>
     */
    protected function found(?array $record, string $message = 'Data tidak ditemukan.'): array
    {
        if ($record === null) {
            abort(404, $message);
        }

        return $record;
    }
}
