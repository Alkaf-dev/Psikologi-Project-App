@php
    use Illuminate\Support\Carbon;

    $statusColor = match ($task['status'] ?? '') {
        'completed' => 'success',
        'draft' => 'gray',
        default => 'info',
    };
    $submissionColor = match ($task['submission_status'] ?? null) {
        'graded' => 'success',
        'late' => 'danger',
        'submitted' => 'info',
        default => 'warning',
    };
    $description = trim((string) ($task['description'] ?? ''));
    $startAt = filled($task['start_at'] ?? null)
        ? Carbon::parse((string) $task['start_at'])->translatedFormat('d M Y H:i')
        : '-';
    $deadline = filled($task['deadline'] ?? null)
        ? Carbon::parse((string) $task['deadline'])->translatedFormat('d M Y H:i')
        : '-';
    $hasLinks = filled($task['material_url'] ?? null) || filled($task['collection_link'] ?? null);
    $showSubmission = array_key_exists('submission_label', $task);
    $border = '1px solid rgba(115, 115, 115, .22)';
    $cardStyle = "border: {$border}; border-radius: .75rem; padding: .8rem 1rem; background: rgba(127, 127, 127, .05);";
    $labelStyle = 'font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: rgb(var(--gray-500)); margin-bottom: .3rem;';
@endphp

<div style="display: grid; gap: 1rem;">
    <div style="display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Pertemuan</div>
            <div style="font-weight: 600; line-height: 1.4;">
                {{ filled($task['meeting_number'] ?? null) ? $task['meeting_number'] : '-' }}
            </div>
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Mata kuliah</div>
            <div style="font-weight: 600; line-height: 1.4;">{{ $task['course_name'] ?: '-' }}</div>
            @if (filled($task['course_code'] ?? null))
                <div style="margin-top: .2rem; font-size: .8rem; color: rgb(var(--gray-500));">
                    {{ $task['course_code'] }}
                </div>
            @endif
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Status</div>
            <x-filament::badge :color="$statusColor">
                {{ $task['status_label'] ?? 'Aktif' }}
            </x-filament::badge>
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Kelompok</div>
            <div style="font-weight: 600; line-height: 1.4;">{{ $task['group_name'] ?: '-' }}</div>
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Tanggal mulai</div>
            <div style="font-weight: 600; line-height: 1.4;">{{ $startAt }}</div>
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Deadline</div>
            <div style="font-weight: 600; line-height: 1.4;">{{ $deadline }}</div>
            <div style="margin-top: .2rem; font-size: .8rem; color: rgb(var(--gray-500));">
                {{ $task['deadline_label'] ?? '' }}
            </div>
        </div>
    </div>

    <div style="border: {{ $border }}; border-radius: .75rem; overflow: hidden;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .65rem 1rem; border-bottom: {{ $border }}; background: rgba(127, 127, 127, .08);">
            <div style="font-weight: 600;">Instruksi tugas</div>
            <x-filament::badge :color="($task['type'] ?? 'individu') === 'kelompok' ? 'info' : 'gray'">
                {{ $task['type_label'] ?? 'Individu' }}
            </x-filament::badge>
        </div>
        <div
            tabindex="0"
            aria-label="Instruksi tugas"
            style="padding: 1rem; max-height: 24rem; overflow-y: auto; white-space: pre-line; line-height: 1.8; overflow-wrap: anywhere;"
        >
            {{ $description !== '' ? $description : 'Tidak ada instruksi tambahan.' }}
        </div>
    </div>

    @if ($hasLinks)
        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Tautan</div>
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
        </div>
    @endif

    @if ($showSubmission)
        <div style="{{ $cardStyle }}">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem;">
                <div style="font-weight: 600;">Pengumpulan</div>
                <x-filament::badge :color="$submissionColor">
                    {{ $task['submission_label'] ?? 'Belum dikumpulkan' }}
                </x-filament::badge>
            </div>

            <div style="display: grid; gap: .55rem; margin-top: .75rem;">
                @if (filled($task['submission_by_label'] ?? null))
                    <div style="font-size: .85rem; color: rgb(var(--gray-500));">
                        {{ $task['submission_by_label'] }}
                    </div>
                @endif

                @if (filled($task['submission_link'] ?? null))
                    <x-filament::link :href="$task['submission_link']" target="_blank" icon="heroicon-o-arrow-top-right-on-square">
                        Lihat tautan pengumpulan
                    </x-filament::link>
                @endif

                @if (filled($task['submission_notes'] ?? null))
                    <div>
                        <div style="{{ $labelStyle }}">Catatan pengumpulan</div>
                        <div style="white-space: pre-line; line-height: 1.7;">{{ $task['submission_notes'] }}</div>
                    </div>
                @endif

                @if (filled($task['submission_grade'] ?? null))
                    <div style="font-size: .9rem;">
                        Nilai: <span style="font-weight: 600;">{{ $task['submission_grade'] }}</span>
                    </div>
                @endif

                @if (filled($task['submission_feedback'] ?? null))
                    <div style="margin-top: .2rem; padding: .75rem; border-left: 3px solid rgb(var(--primary-500)); background: rgba(127, 127, 127, .06);">
                        <div style="{{ $labelStyle }}">Feedback dosen</div>
                        <div style="white-space: pre-line; line-height: 1.7;">{{ $task['submission_feedback'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
