@php
    $record = $getRecord();
    $materialUrl = filled($record['material_url'] ?? null) ? (string) $record['material_url'] : null;
    $collectionUrl = filled($record['collection_link'] ?? null) ? (string) $record['collection_link'] : null;
@endphp

@if (filled($materialUrl) || filled($collectionUrl))
    <div style="display: flex; flex-wrap: wrap; gap: .35rem .9rem;">
        @if (filled($materialUrl))
            <x-filament::link
                :href="$materialUrl"
                target="_blank"
                rel="noopener noreferrer"
                icon="heroicon-o-book-open"
                size="sm"
            >
                Materi
            </x-filament::link>
        @endif

        @if (filled($collectionUrl))
            <x-filament::link
                :href="$collectionUrl"
                target="_blank"
                rel="noopener noreferrer"
                icon="heroicon-o-folder-arrow-down"
                size="sm"
            >
                Pengumpulan
            </x-filament::link>
        @endif
    </div>
@else
    <span style="color: rgb(var(--gray-400));">-</span>
@endif
