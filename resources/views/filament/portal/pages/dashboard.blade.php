<x-filament-panels::page>
    @if (! $student)
        <x-filament::section>
            <p>Data mahasiswa tidak ditemukan. Hubungi admin program studi.</p>
        </x-filament::section>
    @else
        @php
            $pendingTasks = $tasks->filter(fn ($task) => blank($task['submission_status']));
            $nextSchedules = $schedules->take(3);
        @endphp

        <div style="display: grid; gap: 1rem; margin-bottom: 1rem;">
            <x-psychology-mascot-greeting
                context="portal-dashboard"
                :name="$student['name']"
            />

            @if (filled($dashboard_mascot))
                <x-psychology-mascot-gif
                    :mascot="$dashboard_mascot"
                    variant="note"
                />
            @endif
        </div>

        <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));">
            <x-filament::section>
                <div style="font-size: .8rem; color: rgb(var(--gray-500));">Tugas belum dikumpulkan</div>
                <div style="font-size: 2rem; font-weight: 700; line-height: 1.2;">{{ $pendingTasks->count() }}</div>
            </x-filament::section>
            <x-filament::section>
                <div style="font-size: .8rem; color: rgb(var(--gray-500));">Kelompok saya</div>
                <div style="font-size: 2rem; font-weight: 700; line-height: 1.2;">{{ $groups->count() }}</div>
            </x-filament::section>
            <x-filament::section>
                <div style="font-size: .8rem; color: rgb(var(--gray-500));">Prodi</div>
                <div style="font-size: 1.25rem; font-weight: 700; line-height: 1.4;">{{ $prodi_name ?: '-' }}</div>
            </x-filament::section>
            <x-filament::section>
                <div style="font-size: .8rem; color: rgb(var(--gray-500));">Kelas</div>
                <div style="font-size: 1.25rem; font-weight: 700; line-height: 1.4;">{{ $student['class_name'] ?: '-' }}</div>
            </x-filament::section>
            <x-filament::section>
                <div style="font-size: .8rem; color: rgb(var(--gray-500));">NIM</div>
                <div style="font-size: 1.25rem; font-weight: 700; line-height: 1.4;">{{ $student['npm'] ?: '-' }}</div>
            </x-filament::section>
        </div>

        <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-top: 1rem;">
            <x-filament::section heading="Tugas Mendatang">
                @forelse ($pendingTasks->take(5) as $task)
                    <div style="padding: .7rem 0; border-bottom: 1px solid rgb(var(--gray-200));">
                        <div style="display: flex; justify-content: space-between; gap: 1rem;">
                            <div style="font-weight: 600;">{{ $task['title'] }}</div>
                            <x-filament::badge color="warning">Belum</x-filament::badge>
                        </div>
                        <div style="font-size: .8rem; color: rgb(var(--gray-500));">
                            {{ $task['course_name'] }} &middot;
                            {{ $task['deadline'] ? \Illuminate\Support\Carbon::parse($task['deadline'])->translatedFormat('d M Y H:i') : 'Tanpa deadline' }}
                        </div>
                    </div>
                @empty
                    <div class="psychology-empty">
                        <x-psychology-mascot
                            animation="success"
                            size="2.5rem"
                            alt=""
                            aria-hidden="true"
                        />
                        <span>Tidak ada tugas yang menunggu. Kerja bagus!</span>
                    </div>
                @endforelse
                <div style="margin-top: .85rem;">
                    <x-filament::link :href="\App\Filament\Portal\Pages\TugasPage::getUrl()">Lihat semua tugas</x-filament::link>
                </div>
            </x-filament::section>

            <x-filament::section heading="Jadwal Terdekat">
                @forelse ($nextSchedules as $schedule)
                    <div style="display: flex; gap: .75rem; padding: .7rem 0; border-bottom: 1px solid rgb(var(--gray-200));">
                        <x-filament::badge color="info">{{ $schedule['day'] }}</x-filament::badge>
                        <div>
                            <div style="font-weight: 600;">{{ $schedule['course_name'] }}</div>
                            <div style="font-size: .8rem; color: rgb(var(--gray-500));">
                                {{ $schedule['start_time'] }} - {{ $schedule['end_time'] }} &middot;
                                @if (($schedule['mode'] ?? 'offline') === 'online')
                                    Online via {{ $schedule['platform_label'] ?: 'tautan rapat' }}
                                    @if (filled($schedule['meeting_link'] ?? null))
                                        &middot;
                                        <x-filament::link :href="$schedule['meeting_link']" target="_blank">Gabung</x-filament::link>
                                    @endif
                                @else
                                    {{ $schedule['location_label'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p style="color: rgb(var(--gray-500));">Belum ada jadwal untuk Prodi dan kelas Anda.</p>
                @endforelse
                <div style="margin-top: .85rem;">
                    <x-filament::link :href="\App\Filament\Portal\Pages\JadwalPage::getUrl()">Lihat jadwal lengkap</x-filament::link>
                </div>
            </x-filament::section>
        </div>

        <div style="margin-top: 1rem;">
            <x-filament::section heading="Pengumuman Terbaru">
                @forelse ($announcements as $announcement)
                    <div style="padding: .7rem 0; border-bottom: 1px solid rgb(var(--gray-200));">
                        <div style="font-weight: 600;">{{ $announcement['title'] }}</div>
                        <div style="font-size: .8rem; color: rgb(var(--gray-500));">
                            {{ $announcement['published_at'] ? \Illuminate\Support\Carbon::parse($announcement['published_at'])->translatedFormat('d M Y') : '' }}
                        </div>
                        <div style="margin-top: .25rem; white-space: pre-line; color: rgb(var(--gray-600));">
                            {{ \Illuminate\Support\Str::limit($announcement['body'], 180) }}
                        </div>
                    </div>
                @empty
                    <p style="color: rgb(var(--gray-500));">Belum ada pengumuman.</p>
                @endforelse
                <div style="margin-top: .85rem;">
                    <x-filament::link :href="\App\Filament\Portal\Pages\PengumumanPage::getUrl()">Lihat semua pengumuman</x-filament::link>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
