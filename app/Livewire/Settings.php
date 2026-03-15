<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Settings extends Component
{
    public string $name = '';
    public string $email = '';
    public string $timezone = '';
    public string $distance_unit = '';
    public string $temperature_unit = '';
    public string $elevation_unit = '';
    public string $currency = '';
    public bool $debug_mode = false;
    public bool $saved = false;

    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';
    public bool $passwordSaved = false;

    public function mount()
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->timezone = $user->timezone;
        $this->distance_unit = $user->distance_unit;
        $this->temperature_unit = $user->temperature_unit;
        $this->elevation_unit = $user->elevation_unit ?? 'ft';
        $this->currency = $user->currency;
        $this->debug_mode = (bool) $user->debug_mode;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|timezone:all',
            'distance_unit' => 'required|in:mi,km',
            'temperature_unit' => 'required|in:F,C',
            'elevation_unit' => 'required|in:ft,m',
            'currency' => 'required|string|max:3',
        ]);

        Auth::user()->update([
            'name' => $this->name,
            'timezone' => $this->timezone,
            'distance_unit' => $this->distance_unit,
            'temperature_unit' => $this->temperature_unit,
            'elevation_unit' => $this->elevation_unit,
            'currency' => $this->currency,
            'debug_mode' => $this->debug_mode,
        ]);

        $this->saved = true;
        $this->passwordSaved = false;
    }

    public function changePassword()
    {
        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ], [], [
            'new_password' => 'new password',
        ]);

        if (! Hash::check($this->current_password, Auth::user()->password)) {
            $this->addError('current_password', 'The current password is incorrect.');
            return;
        }

        Auth::user()->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        $this->passwordSaved = true;
        $this->saved = false;
        $this->dispatch('password-changed');
    }

    // Backup download and restore handled via dedicated web routes
    // to avoid Livewire memory buffering on large SQLite databases

    public function getBackupsProperty(): array
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/teslog-backup-*.sqlite*');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'date' => filemtime($file),
            ];
        }

        usort($backups, fn ($a, $b) => $b['date'] - $a['date']);

        return array_slice($backups, 0, 10);
    }

    public function deleteBackup(string $filename)
    {
        // Sanitize — only allow expected filename pattern
        if (! preg_match('/^teslog-backup-[\d_-]+\.sqlite(\.gz)?$/', $filename)) {
            return;
        }

        $path = storage_path('backups/' . $filename);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
