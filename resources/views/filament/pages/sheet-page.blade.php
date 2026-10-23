@push('styles')
    <style>
        /* Mode padat untuk tabel Tugas dan Pengumpulan. */
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
            max-width: 100%;
            white-space: normal;
            word-break: break-word;
            line-height: 1.25;
        }

        .task-table-compact .fi-ta-text-item:not(.fi-ta-text-has-badges) {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .task-table-compact .fi-ta-table td,
        .task-table-compact .fi-ta-table th {
            overflow: hidden;
        }

        .submission-table {
            --sheet-table-border: #d9d9d9;
        }

        .submission-table .fi-ta-table {
            min-width: 65rem;
            table-layout: fixed;
        }

        .submission-table .fi-ta-table > tbody > tr {
            height: 3.5rem;
        }

        .submission-table .fi-ta-table > thead > tr > *,
        .submission-table .fi-ta-table > tbody > tr > * {
            border-block-end: 1px solid var(--sheet-table-border);
            vertical-align: middle;
        }

        .submission-table .fi-ta-table > thead > tr > *:not(:last-child),
        .submission-table .fi-ta-table > tbody > tr > *:not(:last-child) {
            border-inline-end: 1px solid var(--sheet-table-border);
        }

        .submission-table .fi-ta-table > thead > tr > th:last-child,
        .submission-table .fi-ta-table > tbody > tr > td:last-child,
        .submission-table .fi-ta-actions-header-cell,
        .submission-table .fi-ta-cell:has(.fi-ta-actions) {
            width: 16.5rem;
            min-width: 16.5rem;
        }

        .submission-table .fi-ta-cell:has(.fi-ta-actions) .fi-ta-actions {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.65rem;
            width: 100%;
        }

        .submission-table .fi-ta-header-cell,
        .submission-table .fi-ta-text:not(.fi-inline),
        .submission-table .fi-ta-cell:has(.fi-ta-actions) {
            padding-inline: 0.75rem;
        }

        .submission-table .fi-ta-text:not(.fi-inline),
        .submission-table .fi-ta-cell:has(.fi-ta-actions) {
            padding-block: 0.625rem;
        }

        .submission-table .fi-ta-table td,
        .submission-table .fi-ta-table th {
            overflow: hidden;
        }

        .submission-table button.fi-ta-col {
            display: block;
            min-width: 0;
            max-width: 100%;
            width: 100%;
            overflow: hidden;
            text-align: inherit;
            cursor: pointer;
        }

        .submission-table button.fi-ta-col:hover .fi-ta-text-item {
            text-decoration: underline;
        }

        .submission-table .fi-ta-col > div,
        .submission-table .fi-ta-text,
        .submission-table .fi-ta-text > div,
        .submission-table .fi-ta-text-item,
        .submission-table .fi-ta-text-item > span,
        .submission-table .fi-ta-text-description {
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
