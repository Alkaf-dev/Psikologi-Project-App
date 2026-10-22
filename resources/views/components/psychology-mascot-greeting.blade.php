@props([
    'context' => 'portal-dashboard',
    'name' => null,
])

@php
    $isAdmin = $context === 'admin-dashboard';
    $greetingTitle = filled($name) ? 'Halo, '.$name.'!' : 'Halo!';
    $greetingMessage = $isAdmin
        ? 'Siap bantu memantau tugas dan pengumpulan hari ini.'
        : 'Jangan lupa cek tenggat terdekat, ya. Saya temani belajarmu.';
    $adminMascotGifPath = 'images/psychology/mascot/melambai.gif';
    $showAdminMascotGif = $isAdmin && is_file(public_path($adminMascotGifPath));
@endphp

<div
    class="psychology-greeting"
    data-mascot-greeting
    data-mascot-greeting-key="{{ $context }}"
>
    @if ($showAdminMascotGif)
        <img
            class="psychology-greeting__image"
            src="{{ asset($adminMascotGifPath) }}"
            alt="Maskot Psikologi Connect melambai"
            width="112"
            height="200"
            decoding="async"
            draggable="false"
        >
    @else
        <x-psychology-mascot
            animation="greeting"
            size="84px"
            class="psychology-greeting__mascot"
            alt="Maskot Psikologi Connect melambai"
        />
    @endif

    <div class="psychology-greeting__copy">
        <p class="psychology-greeting__title">{{ $greetingTitle }}</p>
        <p class="psychology-greeting__message">{{ $greetingMessage }}</p>
    </div>
</div>
