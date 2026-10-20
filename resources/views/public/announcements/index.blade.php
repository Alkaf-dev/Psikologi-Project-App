@extends('public.layouts.app')

@section('title', 'Pengumuman - '.config('app.name'))

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Informasi Program Studi</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl">Pengumuman</h1>
            <p class="mt-3 max-w-2xl text-slate-600">Ikuti informasi terbaru seputar perkuliahan, kegiatan, dan layanan akademik.</p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if ($announcements->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                Belum ada pengumuman yang diterbitkan.
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($announcements as $announcement)
                    @include('public.partials.announcement-card', ['announcement' => $announcement])
                @endforeach
            </div>
        @endif
    </section>
@endsection
