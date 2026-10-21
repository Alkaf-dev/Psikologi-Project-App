<?php

namespace App\Filament\Support;

use App\Repositories\UserRepository;
use App\Support\NotificationCenter;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

abstract class BaseNotificationPage extends Page
{
    protected string $view = 'filament.pages.notifikasi';

    public string $filter = 'all';

    public function mount(): void
    {
        $filter = (string) request()->query('filter', 'all');

        if (in_array($filter, ['all', 'unread'], true)) {
            $this->filter = $filter;
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Tugas aktif pekan ini dan jadwal hari ini serta besok.';
    }

    public function setFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'unread'], true)) {
            $this->filter = $filter;
        }
    }

    public function markAsRead(string $key): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            return;
        }

        app(NotificationCenter::class)->markAsRead($user, $key);
        $this->dispatch('notification-read');
    }

    public function markAllAsRead(): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            return;
        }

        app(NotificationCenter::class)->markAllAsRead($user);
        $this->dispatch('notification-read');

        Notification::make()
            ->title('Semua notifikasi ditandai sudah dibaca')
            ->success()
            ->send();
    }

    public function open(string $key): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            return;
        }

        $notification = app(NotificationCenter::class)
            ->forUser($user)
            ->firstWhere('key', $key);

        if ($notification === null) {
            return;
        }

        app(NotificationCenter::class)->markAsRead($user, $key);
        $this->dispatch('notification-read');

        $this->redirect($notification['url'], navigate: false);
    }

    #[On('notification-read')]
    public function refreshNotifications(): void {}

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = $this->currentUser();
        $notifications = $user !== null
            ? app(NotificationCenter::class)->forUser($user)
            : collect();

        $unreadNotifications = $notifications
            ->where('read', false)
            ->values();

        $visibleNotifications = $this->filter === 'unread'
            ? $unreadNotifications
            : $notifications;

        $groups = collect([
            [
                'key' => 'today',
                'label' => 'Jadwal Hari Ini',
                'description' => 'Jadwal kuliah yang berlangsung hari ini.',
                'icon' => 'heroicon-o-calendar-days',
                'items' => $visibleNotifications->where('group', 'today')->values(),
            ],
            [
                'key' => 'tomorrow',
                'label' => 'Jadwal Besok',
                'description' => 'Persiapan untuk jadwal kuliah besok.',
                'icon' => 'heroicon-o-calendar-days',
                'items' => $visibleNotifications->where('group', 'tomorrow')->values(),
            ],
            [
                'key' => 'tasks',
                'label' => 'Tugas Aktif',
                'description' => 'Tugas aktif pekan ini dan yang deadlinenya masih tersedia.',
                'icon' => 'heroicon-o-clipboard-document-list',
                'items' => $visibleNotifications->where('group', 'tasks')->values(),
            ],
        ])->filter(fn (array $group): bool => $group['items']->isNotEmpty())->values();

        return [
            'notifications' => $visibleNotifications,
            'groups' => $groups,
            'unread_count' => $unreadNotifications->count(),
            'all_count' => $notifications->count(),
            'filter' => $this->filter,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = app(UserRepository::class)->find((string) auth()->id());

        if ($user === null) {
            return null;
        }

        $count = app(NotificationCenter::class)->unreadCount($user);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentUser(): ?array
    {
        return app(UserRepository::class)->find((string) auth()->id());
    }
}
