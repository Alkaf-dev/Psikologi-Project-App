<x-filament-panels::page>
    <style>
        .kelompok-course-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }

        .kelompok-course-card {
            display: flex;
            flex-direction: column;
            gap: .9rem;
            width: 100%;
            padding: 1rem 1.05rem;
            text-align: start;
            color: var(--gray-950);
            cursor: pointer;
            background: var(--color-white);
            border: 1px solid var(--gray-200);
            border-radius: .9rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / .05);
            transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
        }

        .kelompok-course-card:hover {
            border-color: var(--primary-400);
            box-shadow: 0 10px 20px -12px rgb(0 0 0 / .3);
            transform: translateY(-2px);
        }

        .kelompok-course-card:focus-visible {
            outline: 2px solid var(--primary-500);
            outline-offset: 2px;
        }

        .kelompok-course-card-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            flex: none;
            border-radius: .7rem;
            color: var(--primary-700);
            background: var(--primary-50);
        }

        .kelompok-course-card-title {
            font-weight: 700;
            line-height: 1.35;
        }

        .kelompok-course-card-meta {
            margin-top: .3rem;
            font-size: .8rem;
            color: var(--gray-500);
        }

        .kelompok-course-card-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .45rem;
        }

        .kelompok-course-card-arrow {
            color: var(--gray-400);
            transition: color 150ms ease, transform 150ms ease;
        }

        .kelompok-course-card:hover .kelompok-course-card-arrow {
            color: var(--primary-600);
            transform: translateX(2px);
        }

        .dark .kelompok-course-card {
            color: var(--color-white);
            background: var(--gray-900);
            border-color: var(--gray-700);
            box-shadow: 0 1px 2px rgb(0 0 0 / .4);
        }

        .dark .kelompok-course-card:hover {
            border-color: var(--primary-500);
        }

        .dark .kelompok-course-card-icon {
            color: var(--primary-300);
            background: var(--gray-800);
        }

        .dark .kelompok-course-card-meta {
            color: var(--gray-400);
        }

        .dark .kelompok-course-card-arrow {
            color: var(--gray-500);
        }

        .dark .kelompok-course-card:hover .kelompok-course-card-arrow {
            color: var(--primary-400);
        }

        .kelompok-chip-btn {
            overflow: hidden;
            border-radius: 9999px;
            will-change: transform;
            transition:
                transform 140ms cubic-bezier(.2, .9, .2, 1),
                box-shadow 180ms ease,
                background-color 120ms ease,
                color 120ms ease;
        }

        .kelompok-chip-btn:hover {
            transform: translateY(-1px) scale(1.02);
            box-shadow: 0 4px 10px -6px rgb(0 0 0 / .3);
        }

        .kelompok-chip-btn:active {
            transform: scale(.94);
            transition-duration: 60ms;
        }

        .kelompok-chip-btn[aria-current="true"] {
            box-shadow:
                0 0 0 3px var(--primary-200),
                0 5px 14px -7px var(--primary-600);
            animation: kelompok-chip-pop 260ms cubic-bezier(.34, 1.56, .64, 1);
        }

        .kelompok-chip-label,
        .kelompok-chip-count {
            position: relative;
            z-index: 1;
        }

        .kelompok-chip-ripple {
            position: absolute;
            z-index: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 9999px;
            background: currentColor;
            opacity: 0;
            pointer-events: none;
            transform: translate(-50%, -50%) scale(0);
        }

        @keyframes kelompok-chip-pop {
            0% {
                transform: scale(.92);
            }

            60% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .dark .kelompok-chip-btn[aria-current="true"] {
            box-shadow:
                0 0 0 3px var(--primary-800),
                0 5px 14px -7px var(--primary-500);
        }

        @media (prefers-reduced-motion: reduce) {
            .kelompok-chip-btn {
                transition: none;
            }

            .kelompok-chip-btn:hover,
            .kelompok-chip-btn:active {
                transform: none;
            }

            .kelompok-chip-btn[aria-current="true"] {
                animation: none;
            }

            .kelompok-chip-ripple {
                display: none;
            }
        }
    </style>

    @if ($selectedCourse === null)
        @if ($cards === [])
            <x-filament::section>
                <div style="padding: 2rem 1rem; text-align: center;">
                    <div style="font-weight: 700; margin-bottom: .35rem;">Belum ada kelompok</div>
                    <div style="font-size: .875rem; color: var(--gray-500);">
                        {{ $canManage
                            ? 'Tambahkan kelompok untuk mulai mengelola anggota.'
                            : 'Hubungi admin atau dosen jika kelompok Anda belum dibuat.' }}
                    </div>
                </div>
            </x-filament::section>
        @else
            <div class="kelompok-course-grid">
                @foreach ($cards as $card)
                    <button
                        type="button"
                        wire:key="kelompok-course-{{ $card['id'] }}"
                        wire:click="selectCourse('{{ $card['id'] }}')"
                        class="kelompok-course-card"
                    >
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem;">
                            <span class="kelompok-course-card-icon">
                                <x-filament::icon icon="heroicon-o-book-open" style="width: 1.35rem; height: 1.35rem;" />
                            </span>

                            @if (filled($card['code']))
                                <x-filament::badge color="gray">{{ $card['code'] }}</x-filament::badge>
                            @endif
                        </div>

                        <div>
                            <div class="kelompok-course-card-title">{{ $card['name'] }}</div>
                            <div class="kelompok-course-card-meta">
                                {{ collect([$card['class_name'], $card['prodi_name']])->filter()->implode(' · ') }}
                            </div>
                        </div>

                        <div class="kelompok-course-card-footer">
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .45rem;">
                                <x-filament::badge color="info">{{ $card['groups_count'] }} kelompok</x-filament::badge>
                                <x-filament::badge color="gray">{{ $card['members_count'] }} anggota</x-filament::badge>
                            </div>

                            <x-filament::icon
                                icon="heroicon-m-arrow-right"
                                class="kelompok-course-card-arrow"
                                style="width: 1.1rem; height: 1.1rem;"
                            />
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    @else
        <div style="display: grid; gap: 1rem;">
            <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: .85rem;">
                <div style="display: flex; align-items: flex-start; gap: .75rem; min-width: 0;">
                    <x-filament::button
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrow-left"
                        wire:click="backToCourses"
                    >
                        Kembali
                    </x-filament::button>

                    <div style="min-width: 0;">
                        <div style="font-size: 1.1rem; font-weight: 700; line-height: 1.3;">
                            {{ $selectedCourse['name'] }}
                        </div>
                        <div style="margin-top: .2rem; font-size: .8rem; color: var(--gray-500);">
                            {{ collect([$selectedCourse['code'], $selectedCourse['class_name'], $selectedCourse['prodi_name']])->filter()->implode(' · ') }}
                        </div>
                    </div>
                </div>

                @if ($canManage && $selectedGroup)
                    <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
                        <x-filament::button
                            size="sm"
                            icon="heroicon-o-user-plus"
                            wire:click="mountAction('addMember')"
                        >
                            Tambah Anggota
                        </x-filament::button>

                        <x-filament::button
                            size="sm"
                            color="gray"
                            icon="heroicon-o-pencil-square"
                            wire:click="mountAction('edit')"
                        >
                            Ubah Kelompok
                        </x-filament::button>

                        <x-filament::button
                            size="sm"
                            color="danger"
                            icon="heroicon-o-trash"
                            wire:click="mountAction('delete')"
                        >
                            Hapus Kelompok
                        </x-filament::button>
                    </div>
                @endif
            </div>

            @if ($groups->isEmpty())
                <x-filament::section>
                    <div style="padding: 1.5rem 1rem; text-align: center;">
                        <div style="font-weight: 700; margin-bottom: .35rem;">Belum ada kelompok pada mata kuliah ini</div>
                        <div style="font-size: .875rem; color: var(--gray-500);">
                            {{ $canManage
                                ? 'Tambahkan kelompok terlebih dahulu untuk mengelola anggotanya.'
                                : 'Hubungi admin atau dosen jika kelompok Anda belum dibuat.' }}
                        </div>

                        @if ($canManage)
                            <div style="margin-top: .9rem;">
                                <x-filament::button size="sm" icon="heroicon-o-plus" wire:click="mountAction('create')">
                                    Tambah Kelompok
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @else
                <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
                    @foreach ($groups as $group)
                        @php
                            $isActive = $selectedGroup && (string) $selectedGroup['id'] === (string) $group['id'];
                        @endphp

                        <x-filament::button
                            size="sm"
                            :color="$isActive ? 'primary' : 'gray'"
                            :outlined="! $isActive"
                            :loading-indicator="false"
                            :aria-current="$isActive ? 'true' : null"
                            wire:key="kelompok-chip-{{ $group['id'] }}"
                            wire:click="selectGroup('{{ $group['id'] }}')"
                            class="kelompok-chip-btn"
                            x-data="{}"
                            x-on:pointerdown="
                                const bounds = $el.getBoundingClientRect();
                                const ripple = $refs.ripple;

                                ripple.style.left = ($event.clientX - bounds.left) + 'px';
                                ripple.style.top = ($event.clientY - bounds.top) + 'px';

                                ripple.animate(
                                    [
                                        { transform: 'translate(-50%, -50%) scale(0)', opacity: .35 },
                                        { transform: 'translate(-50%, -50%) scale(3.5)', opacity: 0 },
                                    ],
                                    { duration: 360, easing: 'cubic-bezier(.2, .8, .2, 1)' },
                                );
                            "
                        >
                            <span class="kelompok-chip-label">{{ $group['name'] }}</span>
                            <span class="kelompok-chip-count" style="
                                min-width: 1.4rem;
                                padding: .08rem .45rem;
                                border-radius: 9999px;
                                font-size: .7rem;
                                line-height: 1.2;
                                text-align: center;
                                color: {{ $isActive ? 'var(--primary-700)' : 'var(--gray-600)' }};
                                background: {{ $isActive ? 'var(--gray-50)' : 'var(--gray-100)' }};
                            ">
                                {{ $group['members_count'] }}
                            </span>
                            <span x-ref="ripple" class="kelompok-chip-ripple" aria-hidden="true"></span>
                        </x-filament::button>
                    @endforeach
                </div>

                {{ $this->table }}
            @endif
        </div>
    @endif
</x-filament-panels::page>
