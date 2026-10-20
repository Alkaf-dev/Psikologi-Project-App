@extends('public.layouts.app')

@section('title', $announcement['title'].' - '.config('app.name'))

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
        <a href="{{ route('announcements.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">
            &larr; Kembali ke pengumuman
        </a>

        <article class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                <span class="rounded-full bg-teal-50 px-3 py-1 font-semibold text-teal-700">Pengumuman</span>
                <span>{{ $announcement['published_at'] ? \Illuminate\Support\Carbon::parse($announcement['published_at'])->translatedFormat('d F Y H:i') : '' }}</span>
                <span>&middot;</span>
                <span>{{ $announcement['author_name'] ?? 'Admin' }}</span>
            </div>

            <h1 class="mt-5 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{{ $announcement['title'] }}</h1>

            <div class="mt-6 max-w-none whitespace-pre-line text-base leading-8 text-slate-700">
                {{ $announcement['body'] }}
            </div>
        </article>
    </section>
@endsection
