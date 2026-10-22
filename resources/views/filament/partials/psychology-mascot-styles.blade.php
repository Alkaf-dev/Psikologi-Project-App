<style>
    .psychology-mascot {
        --psychology-mascot-size: 120px;
        position: relative;
        display: inline-flex;
        width: var(--psychology-mascot-size);
        height: var(--psychology-mascot-size);
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        line-height: 0;
        vertical-align: middle;
    }

    .psychology-mascot::before {
        position: absolute;
        inset: 14% 10% 5%;
        z-index: 0;
        border-radius: 999px;
        background: radial-gradient(circle, rgb(var(--primary-500) / 0.14) 0%, transparent 72%);
        content: '';
        filter: blur(8px);
        pointer-events: none;
    }

    .psychology-mascot__stage {
        position: relative;
        z-index: 1;
        display: flex;
        width: 100%;
        height: 100%;
        align-items: flex-end;
        justify-content: center;
        transform-origin: 50% 100%;
        will-change: transform;
    }

    .psychology-mascot__image,
    .psychology-mascot__lottie {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        filter: drop-shadow(0 10px 16px rgb(35 25 50 / 0.14));
        user-select: none;
        -webkit-user-drag: none;
    }

    .psychology-mascot__lottie {
        width: 100%;
        height: 100%;
    }

    .psychology-mascot__image[hidden],
    .psychology-mascot__lottie[hidden] {
        display: none !important;
    }

    .psychology-mascot--interactive {
        cursor: pointer;
    }

    .psychology-mascot--interactive:hover .psychology-mascot__stage {
        transform: translateY(-2px) scale(1.02);
    }

    .psychology-greeting {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        padding: 0.8rem 1rem;
        border: 1px solid rgb(var(--gray-200));
        border-radius: 1rem;
        background: rgb(var(--gray-50));
        background: linear-gradient(135deg, rgb(var(--primary-500) / 0.09) 0%, rgb(var(--gray-50)) 62%);
        box-shadow: 0 10px 30px rgb(35 25 50 / 0.04);
    }

    .dark .psychology-greeting {
        border-color: rgb(var(--gray-700));
        background: rgb(var(--gray-900));
        background: linear-gradient(135deg, rgb(var(--primary-500) / 0.12) 0%, rgb(var(--gray-900)) 62%);
        box-shadow: none;
    }

    .psychology-greeting__mascot {
        flex: 0 0 auto;
    }

    .psychology-greeting__copy {
        min-width: 0;
    }

    .psychology-greeting__title {
        margin: 0;
        color: rgb(var(--gray-950));
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .dark .psychology-greeting__title {
        color: rgb(var(--gray-50));
    }

    .psychology-greeting__message {
        margin: 0.15rem 0 0;
        color: rgb(var(--gray-600));
        font-size: 0.82rem;
        line-height: 1.5;
    }

    .dark .psychology-greeting__message {
        color: rgb(var(--gray-400));
    }

    .psychology-empty {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        color: rgb(var(--gray-500));
        font-size: 0.9rem;
    }

    .dark .psychology-empty {
        color: rgb(var(--gray-400));
    }

    .psychology-launcher-spacer {
        height: 10.5rem;
        pointer-events: none;
    }

    .psychology-launcher {
        position: fixed;
        right: 1.25rem;
        bottom: 5rem;
        z-index: 30;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        pointer-events: none;
    }

    .psychology-launcher > * {
        pointer-events: auto;
    }

    .psychology-launcher__toggle {
        position: relative;
        display: inline-flex;
        width: 4.2rem;
        height: 4.2rem;
        align-items: center;
        justify-content: center;
        border: 1px solid rgb(var(--gray-200));
        border-radius: 999px;
        background: rgb(255 255 255);
        background: linear-gradient(145deg, rgb(255 255 255) 0%, rgb(var(--gray-50)) 100%);
        box-shadow: 0 14px 34px rgb(35 25 50 / 0.18);
        cursor: pointer;
        font-family: inherit;
        padding: 0;
        transition: transform 180ms ease, box-shadow 180ms ease;
    }

    .dark .psychology-launcher__toggle {
        border-color: rgb(var(--gray-700));
        background: rgb(var(--gray-900));
        background: linear-gradient(145deg, rgb(var(--gray-800)) 0%, rgb(var(--gray-900)) 100%);
        box-shadow: 0 14px 34px rgb(0 0 0 / 0.4);
    }

    .psychology-launcher__toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 40px rgb(35 25 50 / 0.24);
    }

    .psychology-launcher--celebrate .psychology-launcher__toggle {
        border-color: rgb(var(--primary-400));
        box-shadow: 0 0 0 6px rgb(var(--primary-500) / 0.16), 0 18px 40px rgb(35 25 50 / 0.22);
    }

    .psychology-launcher__toggle:focus-visible,
    .psychology-launcher__close:focus-visible,
    .psychology-launcher__suggestion:focus-visible {
        outline: 2px solid rgb(var(--primary-500));
        outline-offset: 3px;
    }

    .psychology-launcher__pulse {
        position: absolute;
        top: 0.4rem;
        right: 0.45rem;
        width: 0.55rem;
        height: 0.55rem;
        border: 2px solid rgb(255 255 255);
        border-radius: 999px;
        background: rgb(var(--primary-500));
        z-index: 2;
    }

    .dark .psychology-launcher__pulse {
        border-color: rgb(var(--gray-900));
    }

    .psychology-launcher__panel {
        width: min(21rem, calc(100vw - 2rem));
        max-height: min(68vh, 26rem, calc(100vh - 12rem));
        margin-bottom: 0.75rem;
        overflow: auto;
        border: 1px solid rgb(var(--gray-200));
        border-radius: 1rem;
        background: rgb(255 255 255);
        box-shadow: 0 24px 60px rgb(35 25 50 / 0.2);
    }

    .dark .psychology-launcher__panel {
        border-color: rgb(var(--gray-700));
        background: rgb(var(--gray-900));
        box-shadow: 0 24px 60px rgb(0 0 0 / 0.5);
    }

    .psychology-launcher__panel[hidden] {
        display: none !important;
    }

    .psychology-launcher__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.9rem 1rem 0;
    }

    .psychology-launcher__eyebrow {
        margin: 0;
        color: rgb(var(--primary-600));
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        line-height: 1.3;
        text-transform: uppercase;
    }

    .dark .psychology-launcher__eyebrow {
        color: rgb(var(--primary-400));
    }

    .psychology-launcher__title {
        margin: 0.1rem 0 0;
        color: rgb(var(--gray-950));
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .dark .psychology-launcher__title {
        color: rgb(var(--gray-50));
    }

    .psychology-launcher__close {
        display: inline-flex;
        width: 1.8rem;
        height: 1.8rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 999px;
        background: rgb(var(--gray-100));
        color: rgb(var(--gray-600));
        cursor: pointer;
        font-family: inherit;
        font-size: 1.15rem;
        line-height: 1;
        padding: 0;
    }

    .dark .psychology-launcher__close {
        background: rgb(var(--gray-800));
        color: rgb(var(--gray-300));
    }

    .psychology-launcher__body {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 1rem 0;
    }

    .psychology-launcher__mascot {
        flex: 0 0 auto;
    }

    .psychology-launcher__message {
        flex: 1;
        min-width: 0;
        margin: 0;
        color: rgb(var(--gray-600));
        font-size: 0.82rem;
        line-height: 1.5;
    }

    .dark .psychology-launcher__message {
        color: rgb(var(--gray-300));
    }

    .psychology-launcher__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.8rem 1rem 1rem;
    }

    .psychology-launcher__suggestion {
        display: inline-flex;
        align-items: center;
        border: 1px solid rgb(var(--primary-200));
        border-radius: 999px;
        background: rgb(var(--primary-50));
        color: rgb(var(--primary-700));
        cursor: pointer;
        font-family: inherit;
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1.2;
        padding: 0.38rem 0.6rem;
        transition: background-color 150ms ease, border-color 150ms ease;
    }

    .psychology-launcher__suggestion:hover {
        border-color: rgb(var(--primary-300));
        background: rgb(var(--primary-100));
    }

    .dark .psychology-launcher__suggestion {
        border-color: rgb(var(--primary-500) / 0.35);
        background: rgb(var(--primary-500) / 0.14);
        color: rgb(var(--primary-300));
    }

    .dark .psychology-launcher__suggestion:hover {
        border-color: rgb(var(--primary-400) / 0.5);
        background: rgb(var(--primary-500) / 0.22);
    }

    @media (max-width: 640px) {
        .psychology-launcher {
            right: 0.75rem;
            bottom: 4.5rem;
        }

        .psychology-launcher-spacer {
            height: 10rem;
        }

        .psychology-greeting {
            gap: 0.75rem;
            padding: 0.7rem 0.8rem;
        }

        .psychology-launcher__body {
            align-items: flex-start;
        }
    }

    @media (prefers-reduced-motion: no-preference) {
        .psychology-mascot--fallback[data-mascot-state='idle'] .psychology-mascot__stage {
            animation: psychology-mascot-float 4s ease-in-out infinite;
        }

        .psychology-mascot--fallback[data-mascot-state='greeting'] .psychology-mascot__stage {
            animation: psychology-mascot-wave 2.4s ease-in-out infinite;
        }

        .psychology-mascot--fallback[data-mascot-state='studying'] .psychology-mascot__stage {
            animation: psychology-mascot-float 4.8s ease-in-out infinite;
        }

        .psychology-mascot--fallback[data-mascot-state='thinking'] .psychology-mascot__stage {
            animation: psychology-mascot-think 3.2s ease-in-out infinite;
        }

        .psychology-mascot--fallback[data-mascot-state='success'] .psychology-mascot__stage {
            animation: psychology-mascot-celebrate 1.4s ease-in-out 2;
        }

        .psychology-mascot--fallback[data-mascot-state='supportive'] .psychology-mascot__stage {
            animation: psychology-mascot-breathe 3.2s ease-in-out infinite;
        }

        .psychology-mascot--fallback[data-mascot-state='sleepy'] .psychology-mascot__stage {
            animation: psychology-mascot-sleep 4.8s ease-in-out infinite;
        }

        .psychology-launcher__toggle {
            animation: psychology-launcher-arrive 500ms ease-out both;
        }

        .psychology-launcher__panel:not([hidden]) {
            animation: psychology-launcher-pop 180ms ease-out both;
            transform-origin: bottom right;
        }

        .psychology-launcher__pulse {
            animation: psychology-launcher-pulse 2.4s ease-out infinite;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .psychology-mascot__stage,
        .psychology-launcher__toggle,
        .psychology-launcher__panel,
        .psychology-launcher__pulse {
            animation: none !important;
            transition: none !important;
        }

        .psychology-mascot--interactive:hover .psychology-mascot__stage,
        .psychology-launcher__toggle:hover {
            transform: none;
        }
    }

    @keyframes psychology-mascot-float {
        0%,
        100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-4px);
        }
    }

    @keyframes psychology-mascot-wave {
        0%,
        100% {
            transform: translateY(0) rotate(-1.5deg);
        }
        35% {
            transform: translateY(-4px) rotate(2deg);
        }
        70% {
            transform: translateY(-1px) rotate(-1deg);
        }
    }

    @keyframes psychology-mascot-think {
        0%,
        100% {
            transform: translateY(0) rotate(-2deg);
        }
        50% {
            transform: translateY(-3px) rotate(0deg);
        }
    }

    @keyframes psychology-mascot-celebrate {
        0%,
        100% {
            transform: translateY(0) scale(1);
        }
        35% {
            transform: translateY(-9px) scale(1.04) rotate(-2deg);
        }
        65% {
            transform: translateY(-2px) scale(1.01) rotate(2deg);
        }
    }

    @keyframes psychology-mascot-breathe {
        0%,
        100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.025);
        }
    }

    @keyframes psychology-mascot-sleep {
        0%,
        100% {
            transform: translateY(0) scale(1);
        }
        50% {
            transform: translateY(2px) scale(1.015);
        }
    }

    @keyframes psychology-launcher-arrive {
        from {
            opacity: 0;
            transform: translateY(12px) scale(0.96);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes psychology-launcher-pop {
        from {
            opacity: 0;
            transform: translateY(8px) scale(0.97);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes psychology-launcher-pulse {
        0% {
            box-shadow: 0 0 0 0 rgb(var(--primary-500) / 0.35);
        }
        70% {
            box-shadow: 0 0 0 7px rgb(var(--primary-500) / 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgb(var(--primary-500) / 0);
        }
    }
</style>
