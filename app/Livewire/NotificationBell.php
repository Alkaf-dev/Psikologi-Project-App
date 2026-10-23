<?php

namespace App\Livewire;

use App\Repositories\UserRepository;
use App\Support\NotificationCenter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public function open(string $key): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            return;
        }

        $center = app(NotificationCenter::class);
        $notification = $center->forUser($user)->firstWhere('key', $key);

        if ($notification === null) {
            return;
        }

        $center->markAsRead($user, $key);
        $this->dispatch('notification-read');

        $this->redirect($notification['url'], navigate: false);
    }

    public function markAllAsRead(): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            return;
        }

        app(NotificationCenter::class)->markAllAsRead($user);
        $this->dispatch('notification-read');
    }

    #[On('notification-read')]
    public function refreshNotifications(): void {}

    public function render(): View
    {
        $user = $this->currentUser();
        $notifications = collect();
        $unreadCount = 0;
        $pageUrl = '#';

        if ($user !== null) {
            $center = app(NotificationCenter::class);
            $notifications = $center->forUser($user);
            $unreadCount = $notifications->where('read', false)->count();
            $pageUrl = $center->notificationPageUrl();
        }

        return view('livewire.notification-bell', [
            'notifications' => $notifications->take(6),
            'unreadCount' => $unreadCount,
            'pageUrl' => $pageUrl,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentUser(): ?array
    {
        return app(UserRepository::class)->find((string) auth()->id());
    }
}
