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
    $mascotGifPath = 'images/psychology/mascot/melambai.gif';
    $mascotPosterPath = 'images/psychology/mascot/melambai-poster.png';
    $showMascotGif = is_file(public_path($mascotGifPath));
    $showMascotPoster = is_file(public_path($mascotPosterPath));
@endphp

<div
    class="psychology-greeting"
    data-mascot-greeting
    data-mascot-greeting-key="{{ $context }}"
>
    @if ($showMascotGif)
        <picture class="psychology-greeting__picture">
            @if ($showMascotPoster)
                <source
                    srcset="{{ asset($mascotPosterPath) }}"
                    media="(prefers-reduced-motion: reduce)"
                >
            @endif

            <img
                class="psychology-greeting__image"
                src="{{ asset($mascotGifPath) }}"
                alt="Maskot Psikologi Connect melambai"
                width="112"
                height="200"
                decoding="async"
                draggable="false"
            >
        </picture>
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
