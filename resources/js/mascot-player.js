import { DotLottie } from '@lottiefiles/dotlottie-web';

const wasmUrl = window.__psychologyMascotWasmUrl
    || new URL('/vendor/dotlottie/dotlottie-player.wasm', window.location.origin).href;

DotLottie.setWasmUrl(wasmUrl);

import('@lottiefiles/dotlottie-wc').catch(() => {});
