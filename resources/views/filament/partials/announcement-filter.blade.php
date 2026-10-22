@php
    $selectedFilter = $selectedFilter ?? 'all';
    $totalCount = (int) ($totalCount ?? 0);
    $publicCount = (int) ($publicCount ?? 0);
    $courseFilters = $courseFilters ?? [];
    $isAllActive = $selectedFilter === 'all';
    $isPublicActive = $selectedFilter === 'public';
@endphp

<div class="materi-tugas-filter" role="group" aria-label="Filter pengumuman">
    <x-filament::button
        size="sm"
        :color="$isAllActive ? 'primary' : 'gray'"
        :outlined="! $isAllActive"
        :loading-indicator="false"
        :aria-current="$isAllActive ? 'true' : null"
        wire:key="announcement-chip-all"
        wire:click="selectFilter('all')"
        class="materi-tugas-chip"
        title="Semua pengumuman - {{ $totalCount }} pengumuman"
    >
        <span>Semua</span>
        <span class="materi-tugas-chip-count" aria-hidden="true">{{ $totalCount }}</span>
    </x-filament::button>

    <x-filament::button
        size="sm"
        :color="$isPublicActive ? 'primary' : 'gray'"
        :outlined="! $isPublicActive"
        :loading-indicator="false"
        :aria-current="$isPublicActive ? 'true' : null"
        wire:key="announcement-chip-public"
        wire:click="selectFilter('public')"
        class="materi-tugas-chip"
        title="Pengumuman publik untuk semua - {{ $publicCount }} pengumuman"
    >
        <span>Publik</span>
        <span class="materi-tugas-chip-count" aria-hidden="true">{{ $publicCount }}</span>
    </x-filament::button>

    @foreach ($courseFilters as $filter)
        @php
            $isActive = $selectedFilter === (string) $filter['id'];
            $count = (int) ($filter['count'] ?? 0);
        @endphp

        <x-filament::button
            size="sm"
            :color="$isActive ? 'primary' : 'gray'"
            :outlined="! $isActive"
            :loading-indicator="false"
            :aria-current="$isActive ? 'true' : null"
            wire:key="announcement-chip-{{ $filter['id'] }}"
            wire:click="selectFilter('{{ $filter['id'] }}')"
            class="materi-tugas-chip"
            title="{{ $filter['name'] }} - {{ $count }} pengumuman"
        >
            <span>{{ $filter['name'] }}</span>
            <span class="materi-tugas-chip-count" aria-hidden="true">{{ $count }}</span>
        </x-filament::button>
    @endforeach
</div>
