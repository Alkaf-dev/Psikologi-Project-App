@push('styles')
    <style>
        /* Mode padat khusus tabel manajemen tugas. */
        .task-table-compact .fi-ta-text:not(.fi-inline) {
            padding-block: 0.4rem;
        }

        .task-table-compact .fi-ta-header-cell {
            padding-block: 0.5rem;
        }

        .task-table-compact .fi-ta-cell:has(.fi-ta-actions) {
            padding-block: 0.4rem;
        }

        .task-table-compact .fi-ta-text-description {
            margin-top: 0.15rem;
        }
    </style>
@endpush

<x-filament-panels::page>
    @if (filled($this->pageMascot()))
        <x-psychology-mascot-gif :mascot="$this->pageMascot()" class="psychology-page-mascot" />
    @endif

    {{ $this->table }}

    @if ($this->showMascot())
        <div class="psychology-launcher-spacer" aria-hidden="true"></div>
        <x-psychology-mascot-launcher :context="$this->mascotContext()" />
    @endif
</x-filament-panels::page>
