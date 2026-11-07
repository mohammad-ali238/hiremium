<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\InteractsWithWorkspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.dashboard')]
#[Title('Settings')]
class Index extends Component
{
    use InteractsWithWorkspace;
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public $avatar = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $workspaceName = '';

    public bool $notifyBatch = true;

    public bool $notifyCredits = true;

    public string $deletePassword = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->workspaceName = $this->currentWorkspace()?->name ?? '';

        $prefs = $user->notification_prefs ?? [];
        $this->notifyBatch = (bool) ($prefs['batch'] ?? true);
        $this->notifyCredits = (bool) ($prefs['credits'] ?? true);
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', 'dimensions:min_width=200,min_height=200'],
        ]);

        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }
        $user->name = $data['name'];
        $user->email = $data['email'];

        if ($this->avatar) {
            $ext = strtolower($this->avatar->getClientOriginalExtension() ?: 'png');
            $path = $this->avatar->storeAs('avatars', 'user-'.$user->id.'-'.substr(md5(uniqid('', true)), 0, 6).'.'.$ext, 'local');

            if ($user->avatar_path && $user->avatar_path !== $path) {
                Storage::disk('local')->delete($user->avatar_path);
            }
            $user->avatar_path = $path;
            $this->avatar = null;
        }

        $user->save();
        $this->dispatch('toast', type: 'success', message: 'Profile updated.');
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
        $this->dispatch('toast', type: 'info', message: 'Photo removed.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        Auth::user()->update(['password' => Hash::make($this->password)]);
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', type: 'success', message: 'Password changed.');
    }

    public function saveWorkspace(): void
    {
        abort_unless(Auth::user()->can('settings.manage'), 403);

        $this->validate(['workspaceName' => ['required', 'string', 'max:120']]);
        $this->currentWorkspace()?->update(['name' => $this->workspaceName]);
        $this->dispatch('toast', type: 'success', message: 'Workspace updated.');
    }

    public function saveNotifications(): void
    {
        Auth::user()->update(['notification_prefs' => ['batch' => $this->notifyBatch, 'credits' => $this->notifyCredits]]);
        $this->dispatch('toast', type: 'success', message: 'Preferences saved.');
    }

    public function deleteAccount()
    {
        $this->validate(['deletePassword' => ['required', 'current_password']]);

        $user = Auth::user();
        Auth::logout();
        $user->delete();

        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect('/', navigate: false);
    }

    public function render()
    {
        return view('livewire.settings.index', ['canManageWs' => Auth::user()->can('settings.manage')]);
    }
}
