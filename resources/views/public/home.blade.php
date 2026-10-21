@extends('public.layouts.app')

@section('title', config('app.name').' - Portal Akademik Psikologi')

@section('content')
    <section class="relative overflow-hidden bg-gradient-to-br from-teal-700 via-teal-600 to-emerald-500">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 28px 28px;"></div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:py-28">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white/90">
                    Portal Akademik Mahasiswa
                </span>
                <h1 class="mt-5 text-4xl font-bold leading-tight text-white sm:text-5xl">
                    {{ $settings['public_tagline'] ?? 'Ruang belajar, berkolaborasi, dan bertumbuh bersama.' }}
                </h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-teal-50 sm:text-lg">
                    {{ $settings['public_about'] ?? 'Psikologi Connect membantu mahasiswa mengakses jadwal kuliah, tugas, kelompok, dan pengumuman dalam satu tempat.' }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-teal-700 shadow-sm transition hover:bg-teal-50">
                        Masuk Portal Mahasiswa
                    </a>
                    <a href="{{ route('announcements.index') }}" class="rounded-xl border border-white/40 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        Lihat Pengumuman
                    </a>
                </div>
                <p class="mt-5 text-xs text-teal-100/90">
                    {{ $settings['portal_note'] ?? 'Pilih Prodi, lalu masuk dengan email atau NIM untuk melihat aktivitas akademik Anda.' }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-white/20 bg-white/10 p-5 text-white backdrop-blur">
                    <div class="text-xs font-medium uppercase tracking-wider text-teal-100">Tahun Akademik</div>
                    <div class="mt-2 text-2xl font-bold">{{ $settings['academic_year'] ?? '2026/2027' }}</div>
                    <div class="mt-1 text-sm text-teal-100">Semester {{ $settings['semester'] ?? 'Ganjil' }}</div>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/90 p-5 text-slate-800 shadow-lg sm:translate-y-6">
                    <div class="text-xs font-medium uppercase tracking-wider text-slate-500">Program Studi</div>
                    <div class="mt-2 text-xl font-bold">{{ $settings['program_name'] ?? 'Psikologi' }}</div>
                    <div class="mt-2 text-sm text-slate-600">{{ $settings['contact_email'] ?? 'psikologi@example.ac.id' }}</div>
                    <div class="text-sm text-slate-600">{{ $settings['contact_phone'] ?? '' }}</div>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/10 p-5 text-white backdrop-blur sm:col-span-2">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-lg">&#128218;</span>
                        <div>
                            <div class="font-semibold">Satu portal untuk kebutuhan akademik</div>
                            <div class="text-sm text-teal-100">Jadwal, tugas, kelompok, dan pengumuman dalam satu tempat.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="mb-10 max-w-2xl">
            <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">Yang bisa dilakukan di Psikologi Connect</h2>
            <p class="mt-3 text-slate-600">Dirancang agar mahasiswa lebih mudah mengikuti ritme perkuliahan dan kolaborasi kelompok.</p>
        </div>

        <div class="grid gap-5 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-xl">&#128197;</div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900">Jadwal Kuliah</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Lihat jadwal kelas, ruangan atau tautan online, dan dosen pengampu tanpa perlu membuka banyak file.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-xl">&#128221;</div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900">Tugas & Nilai</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Kumpulkan tugas melalui tautan, pantau status pengumpulan, dan lihat feedback dosen.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-xl">&#129309;</div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900">Kelompok</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Ketahui anggota kelompok, ketua, dan tugas kelompok untuk setiap mata kuliah.</p>
            </div>
        </div>
    </section>

    <section class="bg-white py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">Pengumuman Terbaru</h2>
                    <p class="mt-2 text-slate-600">Informasi penting dari program studi.</p>
                </div>
                <a href="{{ route('announcements.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">
                    Semua pengumuman &rarr;
                </a>
            </div>

            @if ($announcements->isEmpty())
                <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                    Belum ada pengumuman yang diterbitkan.
                </div>
            @else
                <div class="mt-8 grid gap-5 md:grid-cols-3">
                    @foreach ($announcements as $announcement)
                        @include('public.partials.announcement-card', ['announcement' => $announcement])
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="rounded-3xl bg-slate-900 px-6 py-10 text-center text-white sm:px-10">
            <h2 class="text-2xl font-bold sm:text-3xl">Siap masuk ke portal?</h2>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-300">
                Gunakan email atau NIM yang terdaftar. Jika belum bisa masuk, hubungi admin program studi.
            </p>
            <a href="{{ route('login') }}" class="mt-6 inline-block rounded-xl bg-teal-400 px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-teal-300">
                Masuk Sekarang
            </a>
        </div>
    </section>
@endsection
