@props([
    'mascot' => null,
    'variant' => 'banner',
    'title' => null,
    'message' => null,
    'alt' => null,
    'loading' => null,
    'poster' => null,
])

@php
    $mascotConfig = is_string($mascot) && $mascot !== ''
        ? config("psychology-mascots.{$mascot}")
        : null;
    $mascotConfig = is_array($mascotConfig) ? $mascotConfig : [];
    $mascotFile = (string) ($mascotConfig['file'] ?? '');
    $mascotPath = $mascotFile !== '' ? public_path($mascotFile) : null;
    $mascotExists = $mascotPath !== null && is_file($mascotPath);
    $mascotPosterFile = (string) ($poster ?? ($mascotConfig['poster'] ?? ''));
    $mascotPosterPath = $mascotPosterFile !== '' ? public_path($mascotPosterFile) : null;
    $mascotPosterExists = $mascotPosterPath !== null && is_file($mascotPosterPath);
    $mascotSize = $mascotExists ? @getimagesize($mascotPath) : false;
    $mascotWidth = is_array($mascotSize) ? (int) $mascotSize[0] : 112;
    $mascotHeight = is_array($mascotSize) ? (int) $mascotSize[1] : 200;
    $mascotDisplayFile = $mascotPosterExists ? $mascotPosterFile : $mascotFile;
    $mascotVariant = in_array($variant, ['banner', 'note', 'empty', 'login'], true)
        ? $variant
        : 'banner';
    $mascotLoading = in_array($loading, ['lazy', 'eager'], true)
        ? $loading
        : (in_array($mascotVariant, ['banner', 'login'], true) ? 'eager' : 'lazy');
    $resolvedTitle = $title ?? ($mascotConfig['title'] ?? null);
    $resolvedMessage = $message ?? ($mascotConfig['message'] ?? null);
    $resolvedAlt = $alt ?? ($mascotConfig['alt'] ?? 'Maskot Psikologi Connect');
@endphp

@if ($mascotExists)
    <div
        {{ $attributes->class([
            'psychology-mascot-gif',
            "psychology-mascot-gif--{$mascotVariant}",
        ]) }}
        data-mascot-gif="{{ $mascot }}"
    >
        <img
            wire:ignore
            class="psychology-mascot-gif__image"
            src="{{ asset($mascotDisplayFile) }}"
            @if ($mascotPosterExists)
                data-mascot-gif-src="{{ asset($mascotFile) }}"
            @endif
            alt="{{ $resolvedAlt }}"
            width="{{ $mascotWidth }}"
            height="{{ $mascotHeight }}"
            loading="{{ $mascotLoading }}"
            decoding="async"
            draggable="false"
        >

        @if (filled($resolvedTitle) || filled($resolvedMessage))
            <div class="psychology-mascot-gif__copy">
                @if (filled($resolvedTitle))
                    <p class="psychology-mascot-gif__title">{{ $resolvedTitle }}</p>
                @endif

                @if (filled($resolvedMessage))
                    <p class="psychology-mascot-gif__message">{{ $resolvedMessage }}</p>
                @endif
            </div>
        @endif
    </div>
@endif
