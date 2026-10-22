<x-filament-panels::page>
    <div style="display: grid; gap: 1rem;">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem;">
            <x-filament::tabs label="Filter notifikasi">
                <x-filament::tabs.item
                    tag="button"
                    :active="$filter === 'all'"
                    :badge="$all_count"
                    badge-color="gray"
                    wire:click="setFilter('all')"
                >
                    Semua
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    tag="button"
                    :active="$filter === 'unread'"
                    :badge="$unread_count"
                    badge-color="danger"
                    wire:click="setFilter('unread')"
                >
                    Belum Dibaca
                </x-filament::tabs.item>
            </x-filament::tabs>

            @if ($unread_count > 0)
                <x-filament::button
                    size="sm"
                    color="gray"
                    icon="heroicon-o-check"
                    wire:click="markAllAsRead"
                    wire:loading.attr="disabled"
                >
                    Tandai semua dibaca
                </x-filament::button>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <x-filament::section>
                <x-psychology-mascot-gif
                    mascot="memeluk-hati"
                    variant="empty"
                    :title="$filter === 'unread' ? 'Semua notifikasi sudah dibaca' : 'Belum ada notifikasi'"
                    :message="$filter === 'unread'
                        ? 'Notifikasi baru akan muncul di sini.'
                        : 'Tugas aktif dan jadwal hari ini atau besok akan tampil di sini.'"
                />
            </x-filament::section>
        @else
            @foreach ($groups as $group)
                <x-filament::section
                    :heading="$group['label']"
                    :description="$group['description']"
                    :icon="$group['icon']"
                    icon-color="primary"
                >
                    <div style="display: grid; gap: .7rem;">
                        @foreach ($group['items'] as $notification)
                            <div
                                wire:key="notification-page-{{ md5($notification['key']) }}"
                                style="
                                    display: flex;
                                    gap: .85rem;
                                    align-items: flex-start;
                                    padding: .9rem 1rem;
                                    border: 1px solid rgb(var(--gray-200));
                                    border-inline-start: 3px solid {{ $notification['read'] ? 'rgb(var(--gray-300))' : 'rgb(var(--primary-500))' }};
                                    border-radius: .75rem;
                                    background: {{ $notification['read'] ? 'transparent' : 'rgb(var(--primary-50))' }};
                                "
                            >
                                <span style="
                                    display: inline-flex;
                                    flex: none;
                                    align-items: center;
                                    justify-content: center;
                                    width: 2.25rem;
                                    height: 2.25rem;
                                    border-radius: 9999px;
                                    color: rgb(var(--{{ $notification['color'] }}-600));
                                    background: rgb(var(--{{ $notification['color'] }}-50));
                                ">
                                    <x-filament::icon :icon="$notification['icon']" style="width: 1.2rem; height: 1.2rem;" />
                                </span>

                                <div style="min-width: 0; flex: 1;">
                                    <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: .5rem;">
                                        <div style="display: flex; align-items: center; gap: .45rem; min-width: 0; font-weight: 700; line-height: 1.35;">
                                            @if (! $notification['read'])
                                                <span style="display: inline-block; flex: none; width: .5rem; height: .5rem; border-radius: 9999px; background: rgb(var(--primary-500));"></span>
                                            @endif
                                            <span>{{ $notification['title'] }}</span>
                                        </div>

                                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .4rem;">
                                            <x-filament::badge :color="$notification['color']">
                                                {{ $notification['badge'] }}
                                            </x-filament::badge>
                                            <span style="font-size: .75rem; color: rgb(var(--gray-500));">
                                                {{ $notification['time_label'] }}
                                            </span>
                                        </div>
                                    </div>

                                    <div style="margin-top: .35rem; font-size: .875rem; line-height: 1.5; color: rgb(var(--gray-600)); white-space: pre-line;">
                                        {{ $notification['description'] }}
                                    </div>

                                    @if (filled($notification['meta']))
                                        <div style="margin-top: .25rem; font-size: .78rem; color: rgb(var(--gray-500));">
                                            {{ $notification['meta'] }}
                                        </div>
                                    @endif

                                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .85rem; margin-top: .55rem;">
                                        <x-filament::link
                                            tag="button"
                                            size="sm"
                                            wire:click="open('{{ $notification['key'] }}')"
                                        >
                                            {{ $notification['action_label'] }}
                                        </x-filament::link>

                                        @if (! $notification['read'])
                                            <x-filament::link
                                                tag="button"
                                                size="sm"
                                                color="gray"
                                                wire:click="markAsRead('{{ $notification['key'] }}')"
                                            >
                                                Tandai dibaca
                                            </x-filament::link>
                                        @else
                                            <span style="font-size: .75rem; color: rgb(var(--gray-400));">Sudah dibaca</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
