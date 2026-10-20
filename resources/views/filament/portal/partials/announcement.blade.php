<div style="display: grid; gap: .75rem;">
    <div style="font-size: .8rem; color: rgb(var(--gray-500));">
        {{ $announcement['published_at'] ? \Illuminate\Support\Carbon::parse($announcement['published_at'])->translatedFormat('d F Y H:i') : '' }}
        &middot; {{ $announcement['author_name'] ?? 'Admin' }}
    </div>
    <div style="white-space: pre-line; line-height: 1.8;">
        {{ $announcement['body'] ?? '' }}
    </div>
</div>
