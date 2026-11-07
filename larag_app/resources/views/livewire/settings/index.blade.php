@php $current = auth()->user()->avatar_url; @endphp
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-app-text">Settings</h1>
        <p class="mt-1 text-sm text-muted">Manage your profile, password, workspace and notifications.</p>
    </div>

    <x-ui.tabs :tabs="['profile' => 'Profile', 'password' => 'Password', 'workspace' => 'Workspace', 'notifications' => 'Notifications']">
        {{-- Profile --}}
        <div x-show="tab === 'profile'">
            <x-ui.card title="Profile">
                <form wire:submit="saveProfile" class="space-y-5">
                    <div class="flex items-center gap-4">
                        <div class="grid h-20 w-20 place-items-center overflow-hidden rounded-full bg-primary-soft">
                            @if ($avatar)
                                <img src="{{ $avatar->temporaryUrl() }}" class="h-full w-full object-cover" alt="Preview">
                            @elseif ($current)
                                <img src="{{ $current }}" class="h-full w-full object-cover" alt="Avatar">
                            @else
                                <span class="text-2xl font-semibold text-primary">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div class="text-sm">
                            <input type="file" wire:model="avatar" accept="image/png,image/jpeg,image/webp"
                                   class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-primary-soft/80">
                            <p class="mt-1 text-xs text-muted">JPG, PNG or WebP · at least 200×200 · max 2 MB. Shown as a circle.</p>
                            <div wire:loading wire:target="avatar" class="mt-1 text-xs text-muted">Uploading…</div>
                            @error('avatar') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                            @if ($current)
                                <button type="button" wire:click="removeAvatar" class="mt-1 text-xs font-medium text-danger hover:underline">Remove photo</button>
                            @endif
                        </div>
                    </div>

                    <x-ui.field label="Name" for="s-name" :error="$errors->first('name')">
                        <x-ui.input id="s-name" wire:model="name" :error="$errors->has('name')" />
                    </x-ui.field>
                    <x-ui.field label="Email" for="s-email" hint="Changing it will require re-verification." :error="$errors->first('email')">
                        <x-ui.input id="s-email" type="email" wire:model="email" :error="$errors->has('email')" />
                    </x-ui.field>

                    <div class="flex justify-end"><x-ui.button type="submit">Save profile</x-ui.button></div>
                </form>
            </x-ui.card>

            {{-- Danger zone --}}
            <x-ui.card title="Delete account" class="mt-6 border-danger/40">
                <p class="text-sm text-muted">Deleting your account is permanent and cannot be undone. Enter your password to confirm.</p>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <x-ui.field label="Password" for="del-pass" class="sm:w-64" :error="$errors->first('deletePassword')">
                        <x-ui.password id="del-pass" wire:model="deletePassword" :error="$errors->has('deletePassword')" />
                    </x-ui.field>
                    <x-ui.button variant="danger"
                                 x-on:click="$store.confirm.ask({ title: 'Delete your account?', text: 'This permanently deletes your account and cannot be undone.', danger: true, confirmText: 'Delete account' }).then(ok => ok && $wire.deleteAccount())">
                        Delete account
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>

        {{-- Password --}}
        <div x-show="tab === 'password'" x-cloak>
            <x-ui.card title="Change password">
                <form wire:submit="updatePassword" class="space-y-5">
                    <x-ui.field label="Current password" for="cp" :error="$errors->first('current_password')">
                        <x-ui.password id="cp" wire:model="current_password" :error="$errors->has('current_password')" />
                    </x-ui.field>
                    <x-ui.field label="New password" for="np" :error="$errors->first('password')">
                        <x-ui.password id="np" wire:model="password" :error="$errors->has('password')" />
                    </x-ui.field>
                    <x-ui.field label="Confirm new password" for="npc">
                        <x-ui.password id="npc" wire:model="password_confirmation" />
                    </x-ui.field>
                    <div class="flex justify-end"><x-ui.button type="submit">Update password</x-ui.button></div>
                </form>
            </x-ui.card>
        </div>

        {{-- Workspace --}}
        <div x-show="tab === 'workspace'" x-cloak>
            <x-ui.card title="Workspace">
                @if ($canManageWs)
                    <form wire:submit="saveWorkspace" class="space-y-5">
                        <x-ui.field label="Workspace name" for="wn" :error="$errors->first('workspaceName')">
                            <x-ui.input id="wn" wire:model="workspaceName" :error="$errors->has('workspaceName')" />
                        </x-ui.field>
                        <div class="flex justify-end"><x-ui.button type="submit">Save workspace</x-ui.button></div>
                    </form>
                @else
                    <p class="text-sm text-muted">Workspace name: <span class="font-medium text-app-text">{{ $workspaceName }}</span></p>
                    <p class="mt-1 text-xs text-muted">Only owners and admins can change workspace settings.</p>
                @endif
            </x-ui.card>
        </div>

        {{-- Notifications --}}
        <div x-show="tab === 'notifications'" x-cloak>
            <x-ui.card title="Email notifications">
                <form wire:submit="saveNotifications" class="space-y-4">
                    <x-ui.switch label="When a resume batch finishes processing" wire:model="notifyBatch" />
                    <x-ui.switch label="When my AI usage is running low" wire:model="notifyCredits" />
                    <p class="text-xs text-muted">Preferences are applied when notifications ship (Phase 11).</p>
                    <div class="flex justify-end"><x-ui.button type="submit">Save preferences</x-ui.button></div>
                </form>
            </x-ui.card>
        </div>
    </x-ui.tabs>
</div>
