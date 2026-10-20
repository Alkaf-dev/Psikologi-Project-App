<article class="group flex h-full flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-md">
    <div>
        <div class="mb-3 flex items-center gap-2 text-xs font-medium text-slate-500">
            <span class="rounded-full bg-teal-50 px-2.5 py-1 text-teal-700">Pengumuman</span>
            <span>{{ $announcement['published_at'] ? \Illuminate\Support\Carbon::parse($announcement['published_at'])->translatedFormat('d M Y') : '' }}</span>
        </div>
        <h3 class="text-lg font-semibold leading-snug text-slate-900 group-hover:text-teal-700">
            <a href="{{ route('announcements.show', $announcement['id']) }}">{{ $announcement['title'] }}</a>
        </h3>
        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">
            {{ \Illuminate\Support\Str::limit((string) ($announcement['body'] ?? ''), 180) }}
        </p>
    </div>
    <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
        <span>{{ $announcement['author_name'] ?? 'Admin' }}</span>
        <a href="{{ route('announcements.show', $announcement['id']) }}" class="font-semibold text-teal-700 hover:text-teal-800">
            Baca selengkapnya &rarr;
        </a>
    </div>
</article>
