<?php

namespace App\Http\Middleware;

use App\Auth\SheetUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof SheetUser) {
            abort(401, 'Unauthenticated.');
        }

        if ($roles === []) {
            return $next($request);
        }

        $allowed = collect($roles)
            ->flatMap(fn (string $role): array => explode(',', $role))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->contains(fn (string $role): bool => match ($role) {
                'staff' => $user->isAdmin(),
                'student' => $user->isStudent(),
                default => false,
            });

        if (! $allowed) {
            abort(403, 'Anda tidak memiliki akses ke resource ini.');
        }

        return $next($request);
    }
}
