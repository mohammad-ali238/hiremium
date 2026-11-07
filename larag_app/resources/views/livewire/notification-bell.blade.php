<div x-data="{ open: false }" class="relative" wire:poll.30s @keydown.escape.window="open = false">
    <button @click="open = ! open" class="relative p-2 rounded-lg text-muted hover:text-app-text hover:bg-primary-soft/60" aria-label="Notifications">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 right-0.5 grid h-4 min-w-[16px] place-items-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition
         class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-line bg-card shadow-lg">
        <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
            <span class="text-sm font-semibold text-app-text">Notifications</span>
            @if ($unreadCount > 0)<button wire:click="markAllRead" class="text-xs font-medium text-primary hover:underline">Mark all read</button>@endif
        </div>
        <ul class="max-h-80 divide-y divide-line overflow-y-auto">
            @forelse ($items as $n)
                @php $unread = $n->read_at === null; @endphp
                <li wire:key="n-{{ $n->id }}">
                    {{-- Unread: accent bar, dot, tinted background, bold title. Read: plain and muted. --}}
                    <button type="button" wire:click="openNotification('{{ $n->id }}')"
                            class="relative block w-full py-3 pl-8 pr-4 text-left transition hover:bg-primary-soft/40
                                   {{ $unread ? 'bg-primary-soft/30' : 'bg-transparent' }}">
                        @if ($unread)
                            <span class="absolute inset-y-0 left-0 w-0.5 bg-primary" aria-hidden="true"></span>
                            <span class="absolute left-3 top-4 h-2 w-2 rounded-full bg-primary" aria-hidden="true"></span>
                        @endif
                        <p class="text-sm {{ $unread ? 'font-semibold text-app-text' : 'font-normal text-app-text/80' }}">{{ $n->data['title'] ?? 'Notification' }}</p>
                        <p class="text-xs {{ $unread ? 'text-app-text/80' : 'text-muted' }}">{{ $n->data['message'] ?? '' }}</p>
                        <p class="mt-0.5 text-[11px] {{ $unread ? 'text-primary' : 'text-muted' }}">
                            {{ $n->created_at?->diffForHumans() }}@if (! $unread) · read @endif
                        </p>
                    </button>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-muted">No notifications yet.</li>
            @endforelse
        </ul>
        @if ($hasMore)
            <div class="border-t border-line px-4 py-2 text-center">
                {{-- Loads the next 10 inline (Livewire request); disappears once everything is loaded. --}}
                <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore"
                        class="text-xs font-medium text-primary hover:underline disabled:opacity-60">
                    <span wire:loading.remove wire:target="loadMore">View more ({{ $remaining }} older)</span>
                    <span wire:loading wire:target="loadMore">Loading…</span>
                </button>
            </div>
        @endif
    </div>
</div>
