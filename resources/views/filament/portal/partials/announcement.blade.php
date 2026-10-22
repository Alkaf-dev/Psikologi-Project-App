<div style="display: grid; gap: .75rem;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .8rem; color: rgb(var(--gray-500));">
        @if (filled($announcement['course_name'] ?? null) && $announcement['course_name'] !== '-')
            <x-filament::badge color="primary">
                {{ $announcement['course_name'] }}
            </x-filament::badge>
        @else
            <x-filament::badge color="gray">
                Publik
            </x-filament::badge>
        @endif
        <span>
            {{ $announcement['published_at'] ? \Illuminate\Support\Carbon::parse($announcement['published_at'])->translatedFormat('d F Y H:i') : '' }}
            &middot; {{ $announcement['author_name'] ?? 'Admin' }}
        </span>
    </div>
    <div style="white-space: pre-line; line-height: 1.8;">
        {{ $announcement['body'] ?? '' }}
    </div>
</div>
