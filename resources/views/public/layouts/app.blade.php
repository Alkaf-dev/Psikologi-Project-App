<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('description', 'Portal akademik Prodi Psikologi STIT Muhammadiyah Kediri untuk jadwal, tugas, kelompok, dan pengumuman.')">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-600 text-sm font-bold text-white shadow-sm">PC</span>
                <span>
                    <span class="block text-lg font-bold leading-tight text-slate-900">{{ config('app.name') }}</span>
                    <span class="block text-xs text-slate-500">{{ config('app.brand_subtitle') }}</span>
                </span>
            </a>

            <nav class="flex flex-wrap items-center gap-1 text-sm font-medium sm:gap-2">
                <a href="{{ route('home') }}"
                   class="rounded-lg px-3 py-2 transition {{ request()->routeIs('home') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    Beranda
                </a>
                <a href="{{ route('announcements.index') }}"
                   class="rounded-lg px-3 py-2 transition {{ request()->routeIs('announcements.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    Pengumuman
                </a>

                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('filament.admin.pages.dashboard') }}"
                           class="ml-1 rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white transition hover:bg-slate-700">
                            Panel Admin
                        </a>
                    @else
                        <a href="{{ route('filament.portal.pages.dashboard') }}"
                           class="ml-1 rounded-lg bg-teal-600 px-4 py-2 font-semibold text-white transition hover:bg-teal-700">
                            Portal Saya
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}"
                       class="ml-1 rounded-lg bg-teal-600 px-4 py-2 font-semibold text-white transition hover:bg-teal-700">
                        Masuk Portal
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ config('app.brand_subtitle') }}.</p>
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('announcements.index') }}" class="hover:text-slate-800">Pengumuman</a>
                <a href="{{ route('login') }}" class="hover:text-slate-800">Masuk Portal</a>
            </div>
        </div>
    </footer>
</body>
</html>
