<?php

namespace App\Livewire;

use App\Support\MapTiles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
    public string $map_provider = '';
    public string $map_api_key = '';
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
        $this->map_provider = $user->map_provider ?? '';
        $this->map_api_key = $user->mapApiKey($this->map_provider) ?? '';
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
            // '' means "follow the server default" rather than a provider choice.
            'map_provider' => ['present', Rule::in(array_merge([''], MapTiles::names()))],
            'map_api_key' => 'nullable|string|max:255',
        ]);

        // Saving a keyed provider without its key would silently fall back to the
        // default basemap, so say so here instead of letting the map change under them.
        if (MapTiles::requiresApiKey($this->map_provider) && trim($this->map_api_key) === '') {
            $this->addError('map_api_key', MapTiles::label($this->map_provider).' requires an API key.');

            return;
        }

        $user = Auth::user();
        $mapProvider = $this->map_provider ?: null;
        $mapApiKey = trim($this->map_api_key) ?: null;
        $mapChanged = $mapProvider !== $user->map_provider
            || ($mapProvider !== null && $mapApiKey !== $user->mapApiKey($mapProvider));

        // Only the selected provider's key is on screen, so leave the others alone.
        if (MapTiles::requiresApiKey($mapProvider)) {
            $user->setMapApiKey($mapProvider, $mapApiKey);
        }

        $user->update([
            'name' => $this->name,
            'timezone' => $this->timezone,
            'distance_unit' => $this->distance_unit,
            'temperature_unit' => $this->temperature_unit,
            'elevation_unit' => $this->elevation_unit,
            'currency' => $this->currency,
            'map_provider' => $mapProvider,
            'map_api_keys' => $user->map_api_keys,
            'debug_mode' => $this->debug_mode,
        ]);

        $this->saved = true;
        $this->passwordSaved = false;

        // Every open map was built with the old tile layer, and Leaflet bakes zoom
        // limits in at construction — a reload is cheaper than patching them live.
        if ($mapChanged) {
            $this->dispatch('map-settings-changed');
        }
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

    public function getMapProvidersProperty(): array
    {
        return MapTiles::options();
    }

    /** Show the key belonging to whichever provider is now selected. */
    public function updatedMapProvider(string $value): void
    {
        $this->resetErrorBag('map_api_key');
        $this->map_api_key = Auth::user()->mapApiKey($value ?: null) ?? '';
    }

    /** The env-configured provider, used while the user has not chosen one. */
    public function getServerDefaultProviderProperty(): string
    {
        return config('teslog.map_tiles')['provider'];
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
