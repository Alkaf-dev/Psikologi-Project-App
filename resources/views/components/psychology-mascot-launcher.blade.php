@props([
    'context' => 'portal-tasks',
])

@php
    $isAdmin = $context === 'admin-tasks';
    $initialMessage = $isAdmin
        ? 'Halo! Saya bisa bantu menjelaskan cara mengelola tugas.'
        : 'Halo! Saya bisa bantu memahami cara mengumpulkan tugas.';
    $tipsMessage = 'Coba bagi tugas besar menjadi langkah kecil, lalu kerjakan 25 menit tanpa distraksi.';
    $supportMessage = 'Kamu sudah sampai sejauh ini. Istirahat singkat juga bagian dari progres.';
    $helpMessage = $isAdmin
        ? 'Gunakan tombol Tambah Tugas untuk membuat tugas baru, atau klik Detail pada baris untuk melihat rincian.'
        : 'Klik Detail untuk membaca instruksi, lalu Kumpulkan untuk mengirim tautan tugas.';
@endphp

<div
    class="psychology-launcher"
    data-mascot-launcher
    data-mascot-context="{{ $context }}"
    data-mascot-initial-state="greeting"
    data-mascot-initial-message="{{ $initialMessage }}"
    data-mascot-tips-message="{{ $tipsMessage }}"
    data-mascot-support-message="{{ $supportMessage }}"
    data-mascot-help-message="{{ $helpMessage }}"
>
    <div
        id="psychology-mascot-panel"
        class="psychology-launcher__panel"
        data-mascot-panel
        role="dialog"
        aria-modal="false"
        aria-labelledby="psychology-mascot-panel-title"
        hidden
    >
        <div class="psychology-launcher__header">
            <div>
                <p class="psychology-launcher__eyebrow">Teman belajar</p>
                <p id="psychology-mascot-panel-title" class="psychology-launcher__title">Maskot Psikologi</p>
            </div>

            <button
                type="button"
                class="psychology-launcher__close"
                data-mascot-close
                aria-label="Tutup bantuan maskot"
            >
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="psychology-launcher__body">
            <x-psychology-mascot
                animation="idle"
                size="6.25rem"
                class="psychology-launcher__mascot"
                alt="Maskot Psikologi Connect"
            />

            <p class="psychology-launcher__message" data-mascot-message aria-live="polite">
                {{ $initialMessage }}
            </p>
        </div>

        <div class="psychology-launcher__actions">
            <button type="button" class="psychology-launcher__suggestion" data-mascot-suggest="tips">
                Tips belajar
            </button>
            <button type="button" class="psychology-launcher__suggestion" data-mascot-suggest="semangat">
                Butuh semangat
            </button>
            <button type="button" class="psychology-launcher__suggestion" data-mascot-suggest="bantuan">
                Cara pakai
            </button>
        </div>
    </div>

    <button
        type="button"
        class="psychology-launcher__toggle"
        data-mascot-toggle
        aria-controls="psychology-mascot-panel"
        aria-expanded="false"
        aria-label="Buka bantuan maskot"
    >
        <span class="psychology-launcher__pulse" aria-hidden="true"></span>
        <x-psychology-mascot
            animation="idle"
            size="3.4rem"
            :interactive="false"
            aria-hidden="true"
        />
    </button>
</div>
