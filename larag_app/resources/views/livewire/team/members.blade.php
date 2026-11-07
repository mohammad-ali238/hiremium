<div class="space-y-6">
    <div>
        <x-ui.breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('app.dashboard')], ['label' => 'Team Members']]" class="mb-2" />
        <h1 class="text-2xl font-bold text-app-text">Team Members</h1>
        <p class="mt-1 text-sm text-muted">Invite people to your workspace and control what they can access.</p>
    </div>

    {{-- Invite --}}
    <x-ui.card title="Invite a member">
        <form wire:submit="invite" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <x-ui.field label="Email address" for="invite-email" class="flex-1" :error="$errors->first('inviteEmail')">
                <x-ui.input id="invite-email" type="email" wire:model="inviteEmail" placeholder="teammate@company.com" :error="$errors->has('inviteEmail')" />
            </x-ui.field>
            <x-ui.field label="Role" for="invite-role" class="sm:w-48">
                <x-ui.select id="invite-role" wire:model="inviteRole">
                    @foreach ($assignableRoles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.button type="submit" wire:loading.attr="disabled">Send invite</x-ui.button>
        </form>
    </x-ui.card>

    {{-- Pending invites --}}
    @if ($invites->isNotEmpty())
        <x-ui.card title="Pending invites" :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($invites as $invite)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-3" wire:key="invite-{{ $invite->id }}"
                        x-data="{ link: '{{ route('invite.show', $invite->token) }}', copied: false }">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-app-text">{{ $invite->email }}</p>
                            <p class="text-xs text-muted">Invited as {{ $invite->role }} · expires {{ $invite->expires_at?->diffForHumans() }}</p>
                        </div>
                        <x-ui.button variant="secondary" size="sm"
                                     x-on:click="navigator.clipboard.writeText(link); copied = true; setTimeout(() => copied = false, 1500)">
                            <span x-show="! copied">Copy link</span>
                            <span x-show="copied" x-cloak>Copied ✓</span>
                        </x-ui.button>
                        <x-ui.button variant="ghost" size="sm"
                                     x-on:click="$store.confirm.ask({ title: 'Revoke invite?', text: 'The link will stop working.', danger: true, confirmText: 'Revoke' }).then(ok => ok && $wire.revokeInvite({{ $invite->id }}))">
                            Revoke
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    {{-- Members --}}
    <x-ui.card title="Members" :padding="false">
        <x-ui.table class="rounded-none border-0">
            <thead>
                <tr>
                    <x-ui.th>Member</x-ui.th>
                    <x-ui.th>Role</x-ui.th>
                    <x-ui.th class="text-right">Actions</x-ui.th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($members as $m)
                    <tr wire:key="member-{{ $m['id'] }}">
                        <x-ui.td>
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$m['name']" size="sm" />
                                <div>
                                    <p class="font-medium text-app-text">{{ $m['name'] }} @if($m['is_self'])<span class="text-xs text-muted">(you)</span>@endif</p>
                                    <p class="text-xs text-muted">{{ $m['email'] }}</p>
                                </div>
                            </div>
                        </x-ui.td>
                        <x-ui.td>
                            @if ($m['is_owner'])
                                <x-ui.badge color="primary">Owner</x-ui.badge>
                            @else
                                <x-ui.select class="h-9 w-40 text-xs"
                                             wire:change="changeRole({{ $m['id'] }}, $event.target.value)">
                                    @foreach ($assignableRoles as $role)
                                        <option value="{{ $role }}" @selected($m['role'] === $role)>{{ $role }}</option>
                                    @endforeach
                                </x-ui.select>
                            @endif
                        </x-ui.td>
                        <x-ui.td class="text-right">
                            @unless ($m['is_owner'] || $m['is_self'])
                                <x-ui.button variant="ghost" size="sm"
                                             x-on:click="$store.confirm.ask({ title: 'Remove {{ $m['name'] }}?', text: 'They will lose access to this workspace.', danger: true, confirmText: 'Remove' }).then(ok => ok && $wire.removeMember({{ $m['id'] }}))">
                                    Remove
                                </x-ui.button>
                            @endunless
                        </x-ui.td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>
</div>
