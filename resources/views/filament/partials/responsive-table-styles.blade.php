<style>
    .sheet-responsive-table {
        --sheet-action-column-width: 14rem;
        --sheet-action-background: #ffffff;
        --sheet-action-header-background: #f9fafb;
        --sheet-action-border: rgba(17, 24, 39, 0.1);
        min-width: 0;
        max-width: 100%;
        width: 100%;
    }

    .dark .sheet-responsive-table {
        --sheet-action-background: #18181b;
        --sheet-action-header-background: #27272a;
        --sheet-action-border: rgba(255, 255, 255, 0.1);
    }

    .materi-tugas-admin-table {
        --sheet-action-column-width: 14rem;
    }

    .materi-tugas-table {
        --sheet-action-column-width: 14.5rem;
    }

    .sheet-responsive-table .fi-ta-ctn,
    .sheet-responsive-table .fi-ta-content-ctn {
        min-width: 0;
        max-width: 100%;
        width: 100%;
        overflow-x: auto;
    }

    .sheet-responsive-table .fi-ta-table {
        width: 100%;
        max-width: 100%;
        table-layout: fixed;
    }

    .materi-tugas-admin-table .fi-ta-table {
        min-width: 107.5rem;
    }

    .materi-tugas-table .fi-ta-table {
        min-width: 94.5rem;
    }

    .submission-table .fi-ta-table {
        min-width: 68rem;
    }

    .sheet-responsive-table .fi-ta-table > thead > tr > *,
    .sheet-responsive-table .fi-ta-table > tbody > tr > * {
        min-width: 0;
        vertical-align: middle;
    }

    .sheet-responsive-table .fi-ta-header-cell {
        vertical-align: middle;
        padding-block: 0.65rem;
        padding-inline: 0.5rem;
        line-height: 1.25;
    }

    .sheet-responsive-table .fi-ta-text:not(.fi-inline),
    .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) {
        padding-inline: 0.5rem;
        padding-block: 0.4rem;
    }

    .materi-tugas-table .fi-ta-text:not(.fi-inline),
    .materi-tugas-table .fi-ta-cell:has(.fi-ta-actions) {
        padding-block: 0.55rem;
    }

    .submission-table .fi-ta-text:not(.fi-inline),
    .submission-table .fi-ta-cell:has(.fi-ta-actions) {
        padding-block: 0.625rem;
    }

    .sheet-responsive-table .fi-ta-col {
        min-width: 0;
        max-width: 100%;
        text-align: inherit;
    }

    .sheet-responsive-table button.fi-ta-col {
        cursor: pointer;
    }

    .sheet-responsive-table button.fi-ta-col:hover .fi-ta-text-item {
        text-decoration: underline;
    }

    .sheet-responsive-table .fi-ta-text {
        min-width: 0;
        max-width: 100%;
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
        min-width: 0;
        max-width: 100%;
    }

    .sheet-responsive-table .fi-badge,
    .sheet-responsive-table .fi-ta-badge {
        max-width: 100%;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .sheet-responsive-table .fi-ta-header-cell.fi-wrapped {
        white-space: normal;
        overflow-wrap: normal;
        word-break: normal;
    }

    .sheet-responsive-table .fi-ta-header-cell .fi-ta-header-cell-sort-btn {
        min-width: 0;
        max-width: 100%;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .sheet-responsive-table .fi-ta-header-cell.fi-wrapped .fi-ta-header-cell-sort-btn {
        flex-wrap: wrap;
        row-gap: 0.15rem;
        white-space: normal;
    }

    .sheet-responsive-table .fi-ta-header-cell.fi-align-center .fi-ta-header-cell-sort-btn {
        justify-content: center;
        margin-inline: auto;
    }

    .sheet-responsive-table .fi-ta-actions-header-cell,
    .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) {
        width: var(--sheet-action-column-width);
        min-width: var(--sheet-action-column-width);
        max-width: var(--sheet-action-column-width);
        position: sticky;
        inset-inline-end: 0;
        background-clip: padding-box;
    }

    .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) {
        z-index: 2;
        background-color: var(--sheet-action-background);
        box-shadow:
            inset 1px 0 0 var(--sheet-action-border),
            -8px 0 12px -8px rgba(0, 0, 0, 0.15);
    }

    .dark .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) {
        box-shadow:
            inset 1px 0 0 var(--sheet-action-border),
            -12px 0 16px -8px rgba(0, 0, 0, 0.6);
    }

    .sheet-responsive-table .fi-ta-actions-header-cell {
        z-index: 3;
        background-color: var(--sheet-action-header-background);
        box-shadow:
            inset 1px 0 0 var(--sheet-action-border),
            -8px 0 12px -8px rgba(0, 0, 0, 0.15);
    }

    .dark .sheet-responsive-table .fi-ta-actions-header-cell {
        box-shadow:
            inset 1px 0 0 var(--sheet-action-border),
            -12px 0 16px -8px rgba(0, 0, 0, 0.6);
    }

    .sheet-responsive-table .fi-ta-cell:has(.fi-ta-actions) .fi-ta-actions {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
    }

    .materi-tugas-table .fi-ta-table > tbody > tr {
        min-height: 3.75rem;
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
