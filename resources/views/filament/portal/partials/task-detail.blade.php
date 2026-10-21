<div style="display: grid; gap: 1rem;">
    <div style="display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;">
        <x-filament::badge color="info">Pertemuan {{ filled($task['meeting_number'] ?? null) ? $task['meeting_number'] : '-' }}</x-filament::badge>
        <x-filament::badge :color="$task['status'] === 'completed' ? 'success' : ($task['status'] === 'draft' ? 'gray' : 'info')">
            {{ $task['status_label'] ?? 'Aktif' }}
        </x-filament::badge>
        <span style="font-size: .85rem; color: rgb(var(--gray-500));">{{ $task['course_name'] ?? '' }}</span>
    </div>

    <div>
        <div style="font-size: .8rem; color: rgb(var(--gray-500));">Kelompok</div>
        <div>{{ $task['group_name'] ?? '-' }}</div>
    </div>

    <div>
        <div style="font-size: .8rem; color: rgb(var(--gray-500));">Tugas / instruksi</div>
        <div style="white-space: pre-line; line-height: 1.7;">{{ $task['description'] ?? '-' }}</div>
    </div>

    <div style="display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div>
            <div style="font-size: .8rem; color: rgb(var(--gray-500));">Tanggal mulai</div>
            <div>{{ filled($task['start_at'] ?? null) ? \Illuminate\Support\Carbon::parse($task['start_at'])->translatedFormat('d M Y H:i') : '-' }}</div>
        </div>
        <div>
            <div style="font-size: .8rem; color: rgb(var(--gray-500));">Deadline</div>
            <div>{{ filled($task['deadline'] ?? null) ? \Illuminate\Support\Carbon::parse($task['deadline'])->translatedFormat('d M Y H:i') : '-' }}</div>
            <div style="font-size: .8rem; color: rgb(var(--gray-500));">{{ $task['deadline_label'] ?? '' }}</div>
        </div>
    </div>

    @if (filled($task['material_url'] ?? null) || filled($task['collection_link'] ?? null))
        <div style="display: flex; flex-wrap: wrap; gap: .75rem;">
            @if (filled($task['material_url'] ?? null))
                <x-filament::link :href="$task['material_url']" target="_blank" icon="heroicon-o-book-open">
                    Buka materi
                </x-filament::link>
            @endif
            @if (filled($task['collection_link'] ?? null))
                <x-filament::link :href="$task['collection_link']" target="_blank" icon="heroicon-o-folder-arrow-down">
                    Folder pengumpulan
                </x-filament::link>
            @endif
        </div>
    @endif

    <div style="border-top: 1px solid rgb(var(--gray-200)); padding-top: .75rem;">
        <div style="font-weight: 600; margin-bottom: .35rem;">Pengumpulan</div>
        <div>{{ $task['submission_label'] ?? 'Belum dikumpulkan' }}</div>
        @if (filled($task['submission_by_label'] ?? null))
            <div style="font-size: .85rem; color: rgb(var(--gray-500));">{{ $task['submission_by_label'] }}</div>
        @endif
        @if (filled($task['submission_link'] ?? null))
            <div style="margin-top: .35rem;">
                <x-filament::link :href="$task['submission_link']" target="_blank" icon="heroicon-o-arrow-top-right-on-square">
                    Lihat tautan pengumpulan
                </x-filament::link>
            </div>
        @endif
        @if (filled($task['submission_notes'] ?? null))
            <div style="margin-top: .35rem; white-space: pre-line; font-size: .9rem;">{{ $task['submission_notes'] }}</div>
        @endif
        @if (filled($task['submission_grade'] ?? null))
            <div style="margin-top: .35rem; font-size: .9rem;">Nilai: {{ $task['submission_grade'] }}</div>
        @endif
    </div>
</div>
