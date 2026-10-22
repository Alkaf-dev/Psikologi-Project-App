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

        .materi-tugas-table .fi-ta-table {
            min-width: 99rem;
            table-layout: fixed;
        }

        .materi-tugas-table .fi-ta-table > tbody > tr {
            height: 3.75rem;
        }

        .materi-tugas-table .fi-ta-table > thead > tr > th:last-child,
        .materi-tugas-table .fi-ta-table > tbody > tr > td:last-child {
            width: 17rem;
        }

        .materi-tugas-table .fi-ta-table > thead > tr > *,
        .materi-tugas-table .fi-ta-table > tbody > tr > * {
            vertical-align: middle;
        }

        .materi-tugas-table .fi-ta-header-cell,
        .materi-tugas-table .fi-ta-text:not(.fi-inline),
        .materi-tugas-table .fi-ta-cell:has(.fi-ta-actions) {
            padding-inline: 0.75rem;
        }

        .materi-tugas-table .fi-ta-text:not(.fi-inline),
        .materi-tugas-table .fi-ta-cell:has(.fi-ta-actions) {
            padding-block: 0.5rem;
        }

        .materi-tugas-table .fi-ta-text-item:not(.fi-ta-text-has-badges) {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .materi-tugas-table .fi-ta-text-description {
            line-height: 1.25;
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

@php
    $selectedCourseId = $selectedCourseId ?? null;
    $totalTasks = (int) ($totalTasks ?? 0);
@endphp

<x-filament-panels::page>
    @if (filled($this->pageMascot()))
        <x-psychology-mascot-gif :mascot="$this->pageMascot()" class="psychology-page-mascot" />
    @endif

    <div class="materi-tugas-filter" role="group" aria-label="Filter mata kuliah">
        @php
            $allDescription = $totalTasks.' tugas';
            $isAllActive = $selectedCourseId === null;
        @endphp

        <x-filament::button
            size="sm"
            :color="$isAllActive ? 'primary' : 'gray'"
            :outlined="! $isAllActive"
            :loading-indicator="false"
            :aria-current="$isAllActive ? 'true' : null"
            wire:key="materi-tugas-chip-all"
            wire:click="selectAllCourses"
            class="materi-tugas-chip"
            title="Semua mata kuliah - {{ $allDescription }}"
        >
            <span>Semua</span>
            <span class="materi-tugas-chip-count" aria-hidden="true">{{ $totalTasks }}</span>
        </x-filament::button>

        @foreach ($courseFilters as $filter)
            @php
                $isActive = $selectedCourseId === (string) $filter['id'];
                $description = $filter['tasks_count'].' tugas';

                if ($filter['materials_count'] > 0) {
                    $description .= ', '.$filter['materials_count'].' materi';
                }
            @endphp

            <x-filament::button
                size="sm"
                :color="$isActive ? 'primary' : 'gray'"
                :outlined="! $isActive"
                :loading-indicator="false"
                :aria-current="$isActive ? 'true' : null"
                wire:key="materi-tugas-chip-{{ $filter['id'] }}"
                wire:click="selectCourse('{{ $filter['id'] }}')"
                class="materi-tugas-chip"
                title="{{ $filter['name'] }} - {{ $description }}"
            >
                <span>{{ $filter['name'] }}</span>
                <span class="materi-tugas-chip-count" aria-hidden="true">{{ $filter['tasks_count'] }}</span>
            </x-filament::button>
        @endforeach
    </div>

    {{ $this->table }}

    @if ($this->showMascot())
        <div class="psychology-launcher-spacer" aria-hidden="true"></div>
        <x-psychology-mascot-launcher :context="$this->mascotContext()" />
    @endif
</x-filament-panels::page>
