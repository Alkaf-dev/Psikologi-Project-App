<x-filament-panels::page>
    @if ($selectedCourse === null)
        @if ($cards === [])
            <x-filament::section>
                <div style="padding: 2rem 1rem; text-align: center;">
                    <div style="font-weight: 700; margin-bottom: .35rem;">Belum ada kelompok</div>
                    <div style="font-size: .875rem; color: rgb(var(--gray-500));">
                        {{ $canManage
                            ? 'Tambahkan kelompok untuk mulai mengelola anggota.'
                            : 'Hubungi admin atau dosen jika kelompok Anda belum dibuat.' }}
                    </div>
                </div>
            </x-filament::section>
        @else
            <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                @foreach ($cards as $card)
                    <button
                        type="button"
                        wire:key="kelompok-course-{{ $card['id'] }}"
                        wire:click="selectCourse('{{ $card['id'] }}')"
                        style="
                            display: flex;
                            flex-direction: column;
                            gap: .85rem;
                            width: 100%;
                            padding: 1rem;
                            text-align: start;
                            color: inherit;
                            cursor: pointer;
                            background: rgb(var(--gray-50));
                            border: 1px solid rgb(var(--gray-200));
                            border-radius: .75rem;
                        "
                    >
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem;">
                            <span style="
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                width: 2.4rem;
                                height: 2.4rem;
                                flex: none;
                                border-radius: .65rem;
                                color: rgb(var(--primary-600));
                                background: rgb(var(--primary-50));
                            ">
                                <x-filament::icon icon="heroicon-o-book-open" style="width: 1.35rem; height: 1.35rem;" />
                            </span>

                            @if (filled($card['code']))
                                <x-filament::badge color="gray">{{ $card['code'] }}</x-filament::badge>
                            @endif
                        </div>

                        <div>
                            <div style="font-weight: 700; line-height: 1.35;">{{ $card['name'] }}</div>
                            <div style="margin-top: .3rem; font-size: .8rem; color: rgb(var(--gray-500));">
                                {{ collect([$card['class_name'], $card['prodi_name']])->filter()->implode(' · ') }}
                            </div>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .45rem;">
                            <x-filament::badge color="info">{{ $card['groups_count'] }} kelompok</x-filament::badge>
                            <x-filament::badge color="gray">{{ $card['members_count'] }} anggota</x-filament::badge>
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
                        <div style="margin-top: .2rem; font-size: .8rem; color: rgb(var(--gray-500));">
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
                        <div style="font-size: .875rem; color: rgb(var(--gray-500));">
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
                            :aria-pressed="$isActive ? 'true' : 'false'"
                            wire:key="kelompok-chip-{{ $group['id'] }}"
                            wire:click="selectGroup('{{ $group['id'] }}')"
                        >
                            <span>{{ $group['name'] }}</span>
                            <span style="
                                min-width: 1.4rem;
                                padding: .08rem .45rem;
                                border-radius: 9999px;
                                font-size: .7rem;
                                line-height: 1.2;
                                text-align: center;
                                color: {{ $isActive ? 'rgb(var(--primary-700))' : 'rgb(var(--gray-600))' }};
                                background: {{ $isActive ? 'rgb(var(--gray-50))' : 'rgb(var(--gray-100))' }};
                            ">
                                {{ $group['members_count'] }}
                            </span>
                        </x-filament::button>
                    @endforeach
                </div>

                {{ $this->table }}
            @endif
        </div>
    @endif
</x-filament-panels::page>
