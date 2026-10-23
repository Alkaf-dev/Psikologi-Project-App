<style>
    .sheet-responsive-table {
        --sheet-action-column-width: 16rem;
    }

    .materi-tugas-admin-table {
        --sheet-action-column-width: 16.5rem;
    }

    .sheet-responsive-table .fi-ta-table {
        width: 100%;
        max-width: 100%;
        table-layout: fixed;
    }

    .materi-tugas-admin-table .fi-ta-table {
        min-width: 88rem;
    }

    .materi-tugas-table .fi-ta-table {
        min-width: 80rem;
    }

    .submission-table .fi-ta-table {
        min-width: 68rem;
    }

    .sheet-responsive-table .fi-ta-table > thead > tr > *,
    .sheet-responsive-table .fi-ta-table > tbody > tr > * {
        min-width: 0;
        vertical-align: middle;
    }

    .sheet-responsive-table .fi-ta-header-cell,
    .sheet-responsive-table .fi-ta-text:not(.fi-inline),
    .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) {
        padding-inline: 0.5rem;
    }

    .sheet-responsive-table .fi-ta-header-cell {
        padding-block: 0.5rem;
    }

    .sheet-responsive-table .fi-ta-text:not(.fi-inline),
    .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) {
        padding-block: 0.4rem;
    }

    .materi-tugas-table .fi-ta-text:not(.fi-inline),
    .materi-tugas-table .fi-ta-cell:has(.fi-ta-actions) {
        padding-block: 0.5rem;
    }

    .submission-table .fi-ta-text:not(.fi-inline),
    .submission-table .fi-ta-cell:has(.fi-ta-actions) {
        padding-block: 0.625rem;
    }

    .sheet-responsive-table .fi-ta-col {
        display: block;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        text-align: inherit;
    }

    .sheet-responsive-table button.fi-ta-col {
        cursor: pointer;
    }

    .sheet-responsive-table button.fi-ta-col:hover .fi-ta-text-item {
        text-decoration: underline;
    }

    .sheet-responsive-table .fi-ta-text {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
    }

    .sheet-responsive-table .fi-ta-text > div,
    .sheet-responsive-table .fi-ta-text > p,
    .sheet-responsive-table .fi-ta-text > ul {
        min-width: 0;
        max-width: 100%;
    }

    .sheet-responsive-table .fi-ta-text:not(.fi-ta-text-has-badges) .fi-ta-text-item {
        display: block;
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sheet-responsive-table .fi-ta-text-description {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        max-width: 100%;
        overflow: hidden;
        margin-top: 0.15rem;
        white-space: normal;
        overflow-wrap: anywhere;
        line-height: 1.25;
    }

    .sheet-responsive-table .fi-ta-text-has-badges {
        overflow: hidden;
    }

    .sheet-responsive-table .fi-ta-text-has-badges .fi-ta-text-item {
        min-width: 0;
        max-width: 100%;
    }

    .sheet-responsive-table .fi-ta-text-has-badges .fi-badge {
        max-width: 100%;
    }

    .sheet-responsive-table .fi-ta-header-cell .fi-ta-header-cell-sort-btn {
        min-width: 0;
        max-width: 100%;
    }

    .sheet-responsive-table .fi-ta-header-cell.fi-wrapped .fi-ta-header-cell-sort-btn {
        white-space: normal;
    }

    .sheet-responsive-table .fi-ta-table > thead > tr > th:last-child,
    .sheet-responsive-table .fi-ta-table > tbody > tr > td:last-child,
    .sheet-responsive-table .fi-ta-actions-header-cell,
    .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) {
        width: var(--sheet-action-column-width);
        min-width: var(--sheet-action-column-width);
        max-width: var(--sheet-action-column-width);
    }

    .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) .fi-ta-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 0.35rem 0.5rem;
        width: 100%;
    }

    .materi-tugas-table .fi-ta-table > tbody > tr {
        height: 3.75rem;
    }

    .submission-table {
        --sheet-table-border: #d9d9d9;
    }

    .dark .submission-table {
        --sheet-table-border: rgba(115, 115, 115, 0.3);
    }

    .submission-table .fi-ta-table > tbody > tr {
        height: 3.5rem;
    }

    .submission-table .fi-ta-table > thead > tr > *,
    .submission-table .fi-ta-table > tbody > tr > * {
        border-block-end: 1px solid var(--sheet-table-border);
    }

    .submission-table .fi-ta-table > thead > tr > *:not(:last-child),
    .submission-table .fi-ta-table > tbody > tr > *:not(:last-child) {
        border-inline-end: 1px solid var(--sheet-table-border);
    }
</style>
