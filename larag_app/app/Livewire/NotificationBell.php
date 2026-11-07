<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Top-bar notification bell — unread count + recent list, embedded in the
 * dashboard layout. Polls for new notifications. "View more" loads the next
 * page (10 at a time) inline without leaving the page.
 */
class NotificationBell extends Component
{
    public const PAGE = 10;

    /** How many notifications are currently shown (grows by PAGE on "View more"). */
    public int $limit = self::PAGE;

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    /** Load the next page of older notifications inline. */
    public function loadMore(): void
    {
        $this->limit += self::PAGE;
    }

    /**
     * NOTE: deliberately NOT named open() — the wrapper is x-data="{ open: false }"
     * (Alpine's panel toggle) and wire:click expressions evaluate in Alpine scope,
     * so a Livewire method called open() resolves to that boolean and throws
     * "open is not a function".
     *
     * Click on a notification: mark it read (the row updates in place — dot and
     * highlight go, unread badge decrements), then go to its target if it has one.
     * A notification without a URL simply becomes read and the panel stays open.
     */
    public function openNotification(string $id)
    {
        $notification = Auth::user()->notifications()->whereKey($id)->first();
        if (! $notification) {
            return null;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $url = $notification->data['url'] ?? null;

        return $url ? $this->redirect($url, navigate: false) : null;
    }

    public function render()
    {
        $user = Auth::user();
        $total = $user->notifications()->count();

        return view('livewire.notification-bell', [
            'unreadCount' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit($this->limit)->get(),
            'hasMore' => $total > $this->limit,
            'remaining' => max(0, $total - $this->limit),
        ]);
    }
}
