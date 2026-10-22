@php
    $mascotHasLottieAssets = collect(['lottie', 'json'])->contains(
        fn (string $extension): bool => (glob(public_path("animations/psychology/*.{$extension}")) ?: []) !== [],
    );
    $mascotPlayerBuilt = false;

    if ($mascotHasLottieAssets) {
        $mascotManifestPath = public_path('build/manifest.json');
        $mascotManifest = is_file($mascotManifestPath)
            ? json_decode((string) file_get_contents($mascotManifestPath), true)
            : [];
        $mascotPlayerBuilt = is_file(public_path('hot'))
            || (is_array($mascotManifest) && isset($mascotManifest['resources/js/mascot-player.js']));
    }
@endphp

@if ($mascotHasLottieAssets && $mascotPlayerBuilt)
    <script>
        window.__psychologyMascotWasmUrl = @json(asset('vendor/dotlottie/dotlottie-player.wasm'));
    </script>
    @vite('resources/js/mascot-player.js')
@endif

@verbatim
    <script>
        (() => {
            if (window.__psychologyMascotBooted) {
                window.__psychologyMascotInit?.();

                return;
            }

            window.__psychologyMascotBooted = true;

            const states = ['idle', 'greeting', 'studying', 'thinking', 'success', 'supportive', 'sleepy'];
            const launcherApis = new WeakMap();
            const stateTimers = new WeakMap();
            const defaultMessage = 'Ada yang bisa saya bantu?';

            const parseAssets = (element) => {
                try {
                    const assets = JSON.parse(element.dataset.mascotAssets || '{}');

                    return assets && typeof assets === 'object' ? assets : {};
                } catch {
                    return {};
                }
            };

            const playerIsReady = () => Boolean(
                window.customElements && window.customElements.get('dotlottie-wc'),
            );

            const prefersReducedMotion = () => Boolean(
                window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            );

            const allowsAnimatedAssets = () => {
                if (prefersReducedMotion()) {
                    return false;
                }

                const connection = navigator.connection;

                if (!connection) {
                    return true;
                }

                if (connection.saveData) {
                    return false;
                }

                return !['slow-2g', '2g', '3g'].includes(connection.effectiveType);
            };

            const whenIdle = (callback) => {
                if (typeof window.requestIdleCallback === 'function') {
                    window.requestIdleCallback(callback, { timeout: 1500 });

                    return;
                }

                window.setTimeout(callback, 250);
            };

            const upgradeDeferredGif = (image) => {
                if (image.dataset.mascotGifLoaded === 'true') {
                    return;
                }

                image.dataset.mascotGifLoaded = 'true';

                if (!image.isConnected || !allowsAnimatedAssets() || !image.dataset.mascotGifSrc) {
                    return;
                }

                const loader = new Image();
                loader.decoding = 'async';
                loader.fetchPriority = 'low';
                loader.onload = () => {
                    if (image.isConnected && allowsAnimatedAssets()) {
                        image.src = image.dataset.mascotGifSrc;
                    }
                };
                loader.src = image.dataset.mascotGifSrc;
            };

            const initDeferredGifs = () => {
                document.querySelectorAll('img[data-mascot-gif-src]').forEach((image) => {
                    if (image.dataset.mascotGifReady === 'true') {
                        return;
                    }

                    image.dataset.mascotGifReady = 'true';

                    if (!allowsAnimatedAssets()) {
                        return;
                    }

                    if (typeof window.IntersectionObserver !== 'function') {
                        whenIdle(() => upgradeDeferredGif(image));

                        return;
                    }

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) {
                                return;
                            }

                            observer.disconnect();
                            whenIdle(() => upgradeDeferredGif(image));
                        });
                    }, { rootMargin: '240px 0px' });

                    observer.observe(image);
                });
            };

            const applyState = (element, requestedState, { allowGif = false } = {}) => {
                const state = states.includes(requestedState) ? requestedState : 'idle';
                const assets = parseAssets(element);
                const asset = assets[state] && typeof assets[state] === 'object' ? assets[state] : {};
                const lottie = element.querySelector('.psychology-mascot__lottie');
                const image = element.querySelector('.psychology-mascot__image');
                const reducedMotion = prefersReducedMotion();
                const useGif = allowGif && Boolean(asset.gif) && !reducedMotion;
                const useLottie = Boolean(asset.lottie) && playerIsReady() && !reducedMotion;

                element.dataset.mascotState = state;
                element.classList.toggle('psychology-mascot--fallback', !useLottie && !useGif);

                if (lottie) {
                    lottie.hidden = !useLottie;

                    if (useLottie && lottie.getAttribute('src') !== asset.lottie) {
                        lottie.setAttribute('src', asset.lottie);
                    }

                    if (!useLottie && reducedMotion) {
                        lottie.removeAttribute('src');
                    }

                    if (state === 'success') {
                        lottie.removeAttribute('loop');
                    } else {
                        lottie.setAttribute('loop', '');
                    }
                }

                if (image) {
                    const imageSource = useGif ? asset.gif : asset.image;

                    if (imageSource && image.getAttribute('src') !== imageSource) {
                        image.setAttribute('src', imageSource);
                    }

                    image.hidden = useLottie && !useGif;
                }
            };

            const scheduleIdle = (element, delay = 6500) => {
                window.clearTimeout(stateTimers.get(element));
                stateTimers.set(element, window.setTimeout(() => {
                    if (!element.isConnected) {
                        return;
                    }

                    const launcher = element.closest('[data-mascot-launcher]');
                    const panel = launcher?.querySelector('[data-mascot-panel]');

                    if (panel && !panel.hidden) {
                        return;
                    }

                    applyState(element, 'idle');
                }, delay));
            };

            const initMascot = (element) => {
                applyState(element, element.dataset.mascotState || 'idle');
            };

            const initGreeting = (element) => {
                if (element.dataset.mascotGreetingReady === 'true') {
                    return;
                }

                element.dataset.mascotGreetingReady = 'true';

                const mascot = element.querySelector('[data-psychology-mascot]');

                if (!mascot) {
                    return;
                }

                const key = `psychology-mascot-greeted:${element.dataset.mascotGreetingKey || 'dashboard'}`;
                let greeted = false;

                try {
                    greeted = window.sessionStorage.getItem(key) === '1';
                } catch {
                    greeted = false;
                }

                if (greeted) {
                    applyState(mascot, 'idle');

                    return;
                }

                applyState(mascot, 'greeting');

                try {
                    window.sessionStorage.setItem(key, '1');
                } catch {}

                window.clearTimeout(stateTimers.get(mascot));
                stateTimers.set(mascot, window.setTimeout(() => {
                    if (mascot.isConnected) {
                        applyState(mascot, 'idle');
                    }
                }, 5600));
            };

            const initLauncher = (launcher) => {
                if (launcher.dataset.mascotLauncherReady === 'true') {
                    return;
                }

                launcher.dataset.mascotLauncherReady = 'true';

                const toggle = launcher.querySelector('[data-mascot-toggle]');
                const panel = launcher.querySelector('[data-mascot-panel]');
                const close = launcher.querySelector('[data-mascot-close]');
                const message = launcher.querySelector('[data-mascot-message]');
                const mascot = toggle?.querySelector('[data-psychology-mascot]');
                const panelMascot = panel?.querySelector('[data-psychology-mascot]');

                if (!toggle || !panel) {
                    return;
                }

                const initialState = states.includes(launcher.dataset.mascotInitialState)
                    ? launcher.dataset.mascotInitialState
                    : 'greeting';
                const initialMessage = launcher.dataset.mascotInitialMessage || defaultMessage;
                let pendingCelebration = null;
                const suggestions = {
                    tips: {
                        state: 'studying',
                        message: launcher.dataset.mascotTipsMessage || initialMessage,
                    },
                    semangat: {
                        state: 'supportive',
                        message: launcher.dataset.mascotSupportMessage || initialMessage,
                    },
                    bantuan: {
                        state: 'thinking',
                        message: launcher.dataset.mascotHelpMessage || initialMessage,
                    },
                };

                const setOpen = (open, focusPanel = false) => {
                    panel.hidden = !open;
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    toggle.setAttribute('aria-label', open ? 'Tutup bantuan maskot' : 'Buka bantuan maskot');

                    if (open && focusPanel) {
                        window.setTimeout(() => {
                            if (!panel.hidden) {
                                close?.focus();
                            }
                        }, 30);
                    }

                    if (open) {
                        if (mascot) {
                            window.clearTimeout(stateTimers.get(mascot));
                            applyState(mascot, 'idle', { allowGif: false });
                        }
                    } else {
                        [mascot, panelMascot].forEach((element) => {
                            if (element) {
                                window.clearTimeout(stateTimers.get(element));
                            }
                        });

                        if (panelMascot) {
                            applyState(panelMascot, 'idle', { allowGif: false });
                        }

                        if (mascot) {
                            applyState(mascot, 'idle', { allowGif: true });
                        }

                        if (message) {
                            message.textContent = initialMessage;
                        }
                    }
                };

                const show = (state, text, focusPanel = false) => {
                    launcher.classList.remove('psychology-launcher--celebrate');

                    setOpen(true, focusPanel);

                    if (panelMascot) {
                        applyState(panelMascot, state, { allowGif: true });
                    }

                    if (message && typeof text === 'string' && text.trim() !== '') {
                        message.textContent = text;
                    }
                };

                toggle.addEventListener('click', () => {
                    if (panel.hidden) {
                        const pending = pendingCelebration;
                        pendingCelebration = null;
                        show(
                            pending?.state || initialState,
                            pending?.message || initialMessage,
                            true,
                        );

                        return;
                    }

                    setOpen(false);
                });

                close?.addEventListener('click', () => {
                    setOpen(false);
                    toggle.focus();
                });

                launcher.querySelectorAll('[data-mascot-suggest]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const suggestion = suggestions[button.dataset.mascotSuggest];

                        if (suggestion) {
                            show(suggestion.state, suggestion.message);
                        }
                    });
                });

                launcherApis.set(launcher, {
                    close: () => {
                        if (!panel.hidden) {
                            setOpen(false);
                        }
                    },
                    celebrate: (state, text) => {
                        if (!panel.hidden) {
                            show(state, text);

                            return;
                        }

                        pendingCelebration = { state, message: text };

                        if (mascot) {
                            window.clearTimeout(stateTimers.get(mascot));
                        }

                        launcher.classList.add('psychology-launcher--celebrate');
                        window.setTimeout(() => {
                            launcher.classList.remove('psychology-launcher--celebrate');
                        }, 3200);
                    },
                });
            };

            const handleCelebrate = (event) => {
                const rawDetail = event.detail;
                const detail = Array.isArray(rawDetail)
                    ? (rawDetail[0] || {})
                    : (rawDetail && typeof rawDetail === 'object' ? rawDetail : {});
                const state = states.includes(detail.state) ? detail.state : 'success';
                const message = typeof detail.message === 'string' && detail.message.trim() !== ''
                    ? detail.message
                    : 'Kerja bagus!';

                document.querySelectorAll('[data-psychology-mascot]').forEach((mascot) => {
                    if (mascot.closest('[data-mascot-launcher]')) {
                        return;
                    }

                    window.clearTimeout(stateTimers.get(mascot));
                    applyState(mascot, state);
                    scheduleIdle(mascot);
                });

                document.querySelectorAll('[data-mascot-launcher]').forEach((launcher) => {
                    launcherApis.get(launcher)?.celebrate(state, message);
                });
            };

            const initAll = () => {
                document.querySelectorAll('[data-psychology-mascot]').forEach(initMascot);
                document.querySelectorAll('[data-mascot-greeting]').forEach(initGreeting);
                document.querySelectorAll('[data-mascot-launcher]').forEach(initLauncher);
                initDeferredGifs();

                document.querySelectorAll('[data-mascot-launcher]').forEach((launcher) => {
                    const panel = launcher.querySelector('[data-mascot-panel]');

                    if (panel && !panel.hidden) {
                        return;
                    }

                    const toggleMascot = launcher.querySelector('[data-mascot-toggle] [data-psychology-mascot]');

                    if (toggleMascot) {
                        applyState(toggleMascot, 'idle', { allowGif: false });
                    }
                });
            };

            window.__psychologyMascotInit = initAll;

            window.addEventListener('psychology-mascot:celebrate', handleCelebrate);

            document.addEventListener('click', (event) => {
                document.querySelectorAll('[data-mascot-launcher]').forEach((launcher) => {
                    if (!launcher.contains(event.target)) {
                        launcherApis.get(launcher)?.close();
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                document.querySelectorAll('[data-mascot-launcher]').forEach((launcher) => {
                    const panel = launcher.querySelector('[data-mascot-panel]');

                    if (panel && !panel.hidden) {
                        launcherApis.get(launcher)?.close();
                        launcher.querySelector('[data-mascot-toggle]')?.focus();
                    }
                });
            });

            if (window.customElements && !window.customElements.get('dotlottie-wc')) {
                window.customElements.whenDefined('dotlottie-wc').then(() => {
                    document.querySelectorAll('[data-psychology-mascot]').forEach(initMascot);
                }).catch(() => {});
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initAll, { once: true });
            } else {
                initAll();
            }

            document.addEventListener('livewire:navigated', initAll);
        })();
    </script>
@endverbatim
