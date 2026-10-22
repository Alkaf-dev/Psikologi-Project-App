@php
    use Illuminate\Support\Carbon;

    $border = '1px solid rgba(115, 115, 115, .22)';
    $cardStyle = "border: {$border}; border-radius: .75rem; padding: .8rem 1rem; background: rgba(127, 127, 127, .05);";
    $labelStyle = 'font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: rgb(var(--gray-500)); margin-bottom: .3rem;';

    $statusColor = match ($submission['status'] ?? 'submitted') {
        'late' => 'danger',
        'graded' => 'success',
        default => 'info',
    };

    $statusLabel = match ($submission['status'] ?? 'submitted') {
        'late' => 'Terlambat',
        'graded' => 'Dinilai',
        default => 'Dikumpulkan',
    };

    $submittedAt = filled($submission['submitted_at'] ?? null)
        ? Carbon::parse((string) $submission['submitted_at'])->translatedFormat('d M Y H:i')
        : '-';

    $link = trim((string) ($submission['link'] ?? ''));
    $hasValidLink = $link !== '' && filter_var($link, FILTER_VALIDATE_URL) !== false;
@endphp

<div style="display: grid; gap: 1rem;">
    <div style="display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Tugas</div>
            <div style="font-weight: 600; line-height: 1.4;">{{ $submission['task_title'] ?? '-' }}</div>
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Mahasiswa / Perwakilan</div>
            <div style="font-weight: 600; line-height: 1.4;">{{ $submission['student_name'] ?? '-' }}</div>
            @if (filled($submission['student_npm'] ?? null))
                <div style="margin-top: .2rem; font-size: .8rem; color: rgb(var(--gray-500));">
                    NPM: {{ $submission['student_npm'] }}
                </div>
            @endif
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Kelompok</div>
            <div style="font-weight: 600; line-height: 1.4;">
                {{ filled($submission['group_name'] ?? null) ? $submission['group_name'] : 'Individu / Semua' }}
            </div>
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Status</div>
            <x-filament::badge :color="$statusColor">
                {{ $statusLabel }}
            </x-filament::badge>
        </div>

        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Waktu Dikumpulkan</div>
            <div style="font-weight: 600; line-height: 1.4;">{{ $submittedAt }}</div>
        </div>
    </div>

    @if ($hasValidLink)
        <div style="{{ $cardStyle }}">
            <div style="{{ $labelStyle }}">Tautan Pengumpulan</div>
            <div style="margin-top: .35rem;">
                <x-filament::link
                    :href="$link"
                    target="_blank"
                    rel="noopener noreferrer"
                    icon="heroicon-o-arrow-top-right-on-square"
                >
                    {{ $link }}
                </x-filament::link>
            </div>
        </div>
    @endif

    @if (filled($submission['notes'] ?? null))
        <div style="border: {{ $border }}; border-radius: .75rem; overflow: hidden;">
            <div style="padding: .65rem 1rem; border-bottom: {{ $border }}; background: rgba(127, 127, 127, .08); font-weight: 600;">
                Catatan Pengumpulan
            </div>
            <div style="padding: 1rem; white-space: pre-line; line-height: 1.7;">
                {{ $submission['notes'] }}
            </div>
        </div>
    @endif
</div>
