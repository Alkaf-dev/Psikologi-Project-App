<div>
    <x-filament::modal
        aria-labelledby="notification-panel.heading"
        id="notification-panel"
        slide-over
        sticky-header
        teleport="body"
        width="md"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                icon="heroicon-o-bell"
                color="gray"
                label="Notifikasi"
                tooltip="Notifikasi"
                :badge="$unreadCount > 0 ? ($unreadCount > 99 ? '99+' : (string) $unreadCount) : null"
                badge-color="danger"
            />
        </x-slot>

        <x-slot name="header">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; width: 100%; padding-inline-end: 2.5rem; pointer-events: none;">
                <div>
                    <h2 id="notification-panel.heading" style="font-size: 1.05rem; font-weight: 700; line-height: 1.4;">
                        Notifikasi
                    </h2>
                    <p style="margin-top: .15rem; font-size: .8rem; color: rgb(var(--gray-500));">
                        {{ $unreadCount > 0 ? $unreadCount.' belum dibaca' : 'Semua sudah dibaca' }}
                    </p>
                </div>

                @if ($unreadCount > 0)
                    <x-filament::link
                        tag="button"
                        size="sm"
                        color="gray"
                        wire:click="markAllAsRead"
                        wire:loading.attr="disabled"
                        style="pointer-events: auto;"
                    >
                        Tandai semua
                    </x-filament::link>
                @endif
            </div>
        </x-slot>

        <div style="display: grid; gap: .7rem;">
            @forelse ($notifications as $notification)
                <button
                    type="button"
                    wire:key="notification-panel-{{ md5($notification['key']) }}"
                    wire:click="open('{{ $notification['key'] }}')"
                    style="
                        display: flex;
                        width: 100%;
                        gap: .85rem;
                        align-items: flex-start;
                        padding: .9rem 1rem;
                        text-align: left;
                        cursor: pointer;
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
                        width: 2.1rem;
                        height: 2.1rem;
                        border-radius: 9999px;
                        color: rgb(var(--{{ $notification['color'] }}-600));
                        background: rgb(var(--{{ $notification['color'] }}-50));
                    ">
                        <x-filament::icon :icon="$notification['icon']" style="width: 1.15rem; height: 1.15rem;" />
                    </span>

                    <span style="display: block; min-width: 0; flex: 1;">
                        <span style="display: flex; align-items: center; gap: .45rem; font-weight: 700; line-height: 1.35;">
                            @if (! $notification['read'])
                                <span style="display: inline-block; flex: none; width: .5rem; height: .5rem; border-radius: 9999px; background: rgb(var(--primary-500));"></span>
                            @endif
                            <span>{{ $notification['title'] }}</span>
                        </span>

                        <span style="display: block; margin-top: .25rem; font-size: .8rem; color: rgb(var(--gray-600)); line-height: 1.5; white-space: pre-line;">
                            {{ $notification['description'] }}
                        </span>

                        <span style="display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin-top: .5rem;">
                            <x-filament::badge :color="$notification['color']" size="sm">
                                {{ $notification['badge'] }}
                            </x-filament::badge>

                            <span style="font-size: .72rem; color: rgb(var(--gray-500));">
                                {{ $notification['time_label'] }}
                            </span>

                            @if (filled($notification['meta']))
                                <span style="font-size: .72rem; color: rgb(var(--gray-500));">
                                    &middot; {{ $notification['meta'] }}
                                </span>
                            @endif
                        </span>

                        <span style="display: block; margin-top: .4rem; font-size: .75rem; font-weight: 600; color: rgb(var(--primary-600));">
                            {{ $notification['action_label'] }}
                        </span>
                    </span>
                </button>
            @empty
                <div style="padding: 2rem 1rem; text-align: center; font-size: .875rem; color: rgb(var(--gray-500));">
                    Tidak ada notifikasi.
                </div>
            @endforelse
        </div>

        <x-slot name="footer">
            <div style="display: flex; justify-content: center; width: 100%;">
                <x-filament::link :href="$pageUrl" size="sm">
                    Lihat semua notifikasi
                </x-filament::link>
            </div>
        </x-slot>
    </x-filament::modal>
</div>
