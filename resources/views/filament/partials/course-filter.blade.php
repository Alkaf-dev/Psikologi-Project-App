@php
    $selectedCourseId = $selectedCourseId ?? null;
    $totalTasks = (int) ($totalTasks ?? 0);
    $courseFilters = $courseFilters ?? [];
    $allDescription = $totalTasks.' tugas';
    $isAllActive = $selectedCourseId === null;
@endphp

<div class="materi-tugas-filter" role="group" aria-label="Filter mata kuliah">
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

            if (($filter['materials_count'] ?? 0) > 0) {
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
