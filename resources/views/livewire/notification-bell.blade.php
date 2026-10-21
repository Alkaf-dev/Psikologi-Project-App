<div>
    <x-filament::dropdown placement="bottom-end" width="sm" teleport>
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

        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 1rem; border-bottom: 1px solid rgba(115, 115, 115, .2);">
            <div>
                <div style="font-weight: 700;">Notifikasi</div>
                <div style="font-size: .75rem; color: rgb(var(--gray-500));">
                    {{ $unreadCount > 0 ? $unreadCount.' belum dibaca' : 'Semua sudah dibaca' }}
                </div>
            </div>

            @if ($unreadCount > 0)
                <x-filament::link
                    tag="button"
                    size="sm"
                    color="gray"
                    wire:click="markAllAsRead"
                    wire:loading.attr="disabled"
                >
                    Tandai semua
                </x-filament::link>
            @endif
        </div>

        <x-filament::dropdown.list>
            @forelse ($notifications as $notification)
                <x-filament::dropdown.list.item
                    tag="button"
                    :icon="$notification['icon']"
                    :icon-color="$notification['color']"
                    wire:key="notification-{{ md5($notification['key']) }}"
                    wire:click="open('{{ $notification['key'] }}')"
                >
                    <span style="display: block; min-width: 0;">
                        <span style="display: flex; align-items: center; gap: .4rem; font-weight: 600; line-height: 1.35;">
                            @if (! $notification['read'])
                                <span style="display: inline-block; width: .45rem; height: .45rem; border-radius: 9999px; background: rgb(var(--danger-500)); flex: none;"></span>
                            @endif
                            <span>{{ $notification['title'] }}</span>
                        </span>
                        <span style="display: block; margin-top: .2rem; font-size: .75rem; color: rgb(var(--gray-500)); line-height: 1.45; white-space: pre-line;">
                            {{ $notification['description'] }}
                        </span>
                        <span style="display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin-top: .35rem; font-size: .72rem; color: rgb(var(--gray-500));">
                            <span>{{ $notification['badge'] }}</span>
                            @if (filled($notification['meta']))
                                <span>&middot;</span>
                                <span>{{ $notification['meta'] }}</span>
                            @endif
                        </span>
                    </span>
                </x-filament::dropdown.list.item>
            @empty
                <div style="padding: 1.5rem 1rem; text-align: center; font-size: .85rem; color: rgb(var(--gray-500));">
                    Tidak ada notifikasi.
                </div>
            @endforelse
        </x-filament::dropdown.list>

        <div style="padding: .65rem 1rem; border-top: 1px solid rgba(115, 115, 115, .2); text-align: center;">
            <x-filament::link :href="$pageUrl" size="sm">
                Lihat semua notifikasi
            </x-filament::link>
        </div>
    </x-filament::dropdown>
</div>
