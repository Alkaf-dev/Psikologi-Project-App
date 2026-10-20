<x-filament-panels::page>
    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));">
        <x-filament::section>
            <div style="font-size: .8rem; color: rgb(var(--gray-500));">Mahasiswa</div>
            <div style="font-size: 2rem; font-weight: 700; line-height: 1.2;">{{ $studentCount }}</div>
        </x-filament::section>
        <x-filament::section>
            <div style="font-size: .8rem; color: rgb(var(--gray-500));">Mata Kuliah</div>
            <div style="font-size: 2rem; font-weight: 700; line-height: 1.2;">{{ $courseCount }}</div>
        </x-filament::section>
        <x-filament::section>
            <div style="font-size: .8rem; color: rgb(var(--gray-500));">Kelompok</div>
            <div style="font-size: 2rem; font-weight: 700; line-height: 1.2;">{{ $groupCount }}</div>
        </x-filament::section>
        <x-filament::section>
            <div style="font-size: .8rem; color: rgb(var(--gray-500));">Tugas</div>
            <div style="font-size: 2rem; font-weight: 700; line-height: 1.2;">{{ $taskCount }}</div>
        </x-filament::section>
        <x-filament::section>
            <div style="font-size: .8rem; color: rgb(var(--gray-500));">Menunggu Penilaian</div>
            <div style="font-size: 2rem; font-weight: 700; line-height: 1.2;">{{ $pendingCount }}</div>
        </x-filament::section>
    </div>

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-top: 1rem;">
        <x-filament::section heading="Tugas Terbaru">
            @forelse ($latestTasks as $task)
                <div style="display: flex; justify-content: space-between; gap: 1rem; padding: .65rem 0; border-bottom: 1px solid rgb(var(--gray-200));">
                    <div>
                        <div style="font-weight: 600;">{{ $task['title'] }}</div>
                        <div style="font-size: .8rem; color: rgb(var(--gray-500));">{{ $task['course_name'] }}</div>
                    </div>
                    <div style="font-size: .8rem; color: rgb(var(--gray-500)); white-space: nowrap;">
                        {{ $task['deadline'] ? \Illuminate\Support\Carbon::parse($task['deadline'])->format('d M Y H:i') : '-' }}
                    </div>
                </div>
            @empty
                <p style="color: rgb(var(--gray-500));">Belum ada tugas.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Pengumpulan Terbaru">
            @forelse ($latestSubmissions as $submission)
                <div style="display: flex; justify-content: space-between; gap: 1rem; padding: .65rem 0; border-bottom: 1px solid rgb(var(--gray-200));">
                    <div>
                        <div style="font-weight: 600;">{{ $submission['student_name'] }}</div>
                        <div style="font-size: .8rem; color: rgb(var(--gray-500));">{{ $submission['task_title'] }}</div>
                    </div>
                    <x-filament::badge color="{{ $submission['status'] === 'graded' ? 'success' : 'warning' }}">
                        {{ $submission['status'] === 'graded' ? 'Dinilai' : 'Dikumpulkan' }}
                    </x-filament::badge>
                </div>
            @empty
                <p style="color: rgb(var(--gray-500));">Belum ada pengumpulan.</p>
            @endforelse
        </x-filament::section>
    </div>

    <div style="margin-top: 1rem;">
        <x-filament::section heading="Akses Cepat">
            <div style="display: flex; flex-wrap: wrap; gap: .75rem;">
                <x-filament::button tag="a" :href="\App\Filament\Pages\StudentPage::getUrl()" color="gray" icon="heroicon-o-identification">
                    Pendataan Mahasiswa
                </x-filament::button>
                <x-filament::button tag="a" :href="\App\Filament\Pages\UserPage::getUrl()" color="gray" icon="heroicon-o-users">
                    Kelola User
                </x-filament::button>
                <x-filament::button tag="a" :href="\App\Filament\Pages\TugasPage::getUrl()" color="gray" icon="heroicon-o-clipboard-document-list">
                    Kelola Tugas
                </x-filament::button>
                <x-filament::button tag="a" :href="\App\Filament\Pages\PengumumanPage::getUrl()" color="gray" icon="heroicon-o-megaphone">
                    Pengumuman
                </x-filament::button>
                <x-filament::badge color="info" style="align-self: center;">Sumber data: {{ $dataDriver }}</x-filament::badge>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
