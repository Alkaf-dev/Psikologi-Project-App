@push('styles')
    <style>
        .materi-tugas-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .materi-tugas-chip {
            border-radius: 9999px;
            white-space: nowrap;
            transition: transform 140ms ease, box-shadow 180ms ease;
        }

        .materi-tugas-chip:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px -6px rgb(0 0 0 / 0.3);
        }

        .materi-tugas-chip:active {
            transform: scale(0.96);
        }

        .materi-tugas-chip[aria-current="true"] {
            box-shadow:
                0 0 0 3px var(--primary-200),
                0 5px 14px -7px var(--primary-600);
        }

        .materi-tugas-chip-count {
            min-width: 1.4rem;
            padding: 0.08rem 0.45rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            line-height: 1.2;
            text-align: center;
            color: inherit;
            background: rgb(127 127 127 / 0.18);
        }

        .materi-tugas-chip[aria-current="true"] .materi-tugas-chip-count {
            background: rgb(255 255 255 / 0.22);
        }

        .dark .materi-tugas-chip[aria-current="true"] {
            box-shadow:
                0 0 0 3px var(--primary-800),
                0 5px 14px -7px var(--primary-500);
        }

        @media (prefers-reduced-motion: reduce) {
            .materi-tugas-chip {
                transition: none;
            }

            .materi-tugas-chip:hover,
            .materi-tugas-chip:active {
                transform: none;
            }
        }
    </style>
@endpush

<x-filament-panels::page>
    @if (filled($this->pageMascot()))
        <x-psychology-mascot-gif :mascot="$this->pageMascot()" class="psychology-page-mascot" />
    @endif

    @include('filament.partials.announcement-filter', [
        'selectedFilter' => $selectedFilter,
        'totalCount' => $totalCount,
        'publicCount' => $publicCount,
        'courseFilters' => $courseFilters,
    ])

    {{ $this->table }}

    @if ($this->showMascot())
        <div class="psychology-launcher-spacer" aria-hidden="true"></div>
        <x-psychology-mascot-launcher :context="$this->mascotContext()" />
    @endif
</x-filament-panels::page>
