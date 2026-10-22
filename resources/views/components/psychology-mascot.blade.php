@props([
    'animation' => 'idle',
    'size' => '120px',
    'alt' => 'Maskot Psikologi Connect',
    'label' => null,
    'interactive' => false,
    'gifAssets' => [],
])

@php
    $mascotStates = ['idle', 'greeting', 'studying', 'thinking', 'success', 'supportive', 'sleepy'];
    $mascotState = in_array($animation, $mascotStates, true) ? $animation : 'idle';
    $mascotLoops = $mascotState !== 'success';
    $mascotSize = is_numeric($size)
        ? trim((string) $size).'px'
        : (is_string($size) && preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|%)$/', trim($size))
            ? trim($size)
            : '120px');

    $mascotAssets = [];
    $gifAssets = is_array($gifAssets) ? $gifAssets : [];

    foreach ($mascotStates as $mascotAssetState) {
        $lottieFile = collect(['lottie', 'json'])
            ->map(fn (string $extension): string => public_path("animations/psychology/{$mascotAssetState}.{$extension}"))
            ->first(fn (string $path): bool => is_file($path));
        $fallbackFile = public_path("images/psychology/mascot/{$mascotAssetState}.png");
        $gifAsset = is_array($gifAssets[$mascotAssetState] ?? null)
            ? $gifAssets[$mascotAssetState]
            : [];
        $gifFile = (string) ($gifAsset['gif'] ?? '');
        $posterFile = (string) ($gifAsset['poster'] ?? '');

        $mascotAssets[$mascotAssetState] = [
            'lottie' => $lottieFile
                ? asset('animations/psychology/'.$mascotAssetState.'.'.pathinfo($lottieFile, PATHINFO_EXTENSION))
                : null,
            'image' => $posterFile !== '' && is_file(public_path($posterFile))
                ? asset($posterFile)
                : (is_file($fallbackFile) ? asset('images/psychology/mascot/'.$mascotAssetState.'.png') : null),
            'gif' => $gifFile !== '' && is_file(public_path($gifFile))
                ? asset($gifFile)
                : null,
        ];
    }

    $mascotCurrent = $mascotAssets[$mascotState];
    $mascotImage = $mascotCurrent['image'] ?? $mascotAssets['idle']['image'] ?? null;
@endphp

<span
    {{ $attributes->class([
        'psychology-mascot',
        'psychology-mascot--interactive' => $interactive,
        'psychology-mascot--fallback' => ! $mascotCurrent['lottie'],
    ])->merge([
        'role' => 'img',
        'aria-label' => $label ?? $alt,
        'style' => "--psychology-mascot-size: {$mascotSize};",
    ]) }}
    data-psychology-mascot
    data-mascot-state="{{ $mascotState }}"
    data-mascot-assets="{{ json_encode($mascotAssets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
>
    <span class="psychology-mascot__stage" data-mascot-stage aria-hidden="true">
        <dotlottie-wc
            class="psychology-mascot__lottie"
            @if ($mascotCurrent['lottie'])
                src="{{ $mascotCurrent['lottie'] }}"
            @endif
            hidden
            autoplay
            @if ($mascotLoops) loop @endif
        ></dotlottie-wc>

        @if ($mascotImage)
            <img
                class="psychology-mascot__image"
                src="{{ $mascotImage }}"
                alt=""
                loading="lazy"
                decoding="async"
                draggable="false"
            >
        @endif
    </span>
</span>
