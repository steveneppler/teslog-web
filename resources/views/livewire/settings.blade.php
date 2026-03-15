<div>
    <form wire:submit="save" class="max-w-2xl space-y-6">
        @if($saved)
            <div class="rounded-lg border border-green-800 bg-green-900/30 p-3 text-sm text-green-300">
                Settings saved successfully.
            </div>
        @endif

        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-lg font-semibold">Profile</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Name</label>
                    <input type="text" wire:model="name"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Email</label>
                    <input type="email" disabled value="{{ $email }}"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt/50 px-4 py-2 text-text-subtle">
                </div>
                <div class="pt-2">
                    <button type="button" x-data x-on:click="$dispatch('open-password-modal')"
                        class="rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-sm font-medium text-text-secondary hover:bg-elevated hover:text-text-primary">
                        Change Password
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-lg font-semibold">Preferences</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Timezone</label>
                    <select wire:model="timezone"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Distance</label>
                    <select wire:model="distance_unit"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                        <option value="mi">Miles</option>
                        <option value="km">Kilometers</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Temperature</label>
                    <select wire:model="temperature_unit"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                        <option value="F">Fahrenheit</option>
                        <option value="C">Celsius</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Elevation</label>
                    <select wire:model="elevation_unit"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                        <option value="ft">Feet</option>
                        <option value="m">Meters</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Currency</label>
                    <input type="text" wire:model="currency" maxlength="3"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                </div>
            </div>
        </div>

        {{-- Tesla Connection --}}
        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-lg font-semibold">Tesla Connection</h3>
        @php
            $connectedVehicles = auth()->user()->vehicles()->whereNotNull('tesla_vehicle_id')->get();
            $isConnected = $connectedVehicles->isNotEmpty();
        @endphp
        @if($isConnected)
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                    <span class="text-sm text-text-secondary">Connected — {{ $connectedVehicles->pluck('name')->join(', ') }}</span>
                </div>
                @php $tokenExpiry = $connectedVehicles->first()->tesla_token_expires_at; @endphp
                @if($tokenExpiry)
                    <p class="text-xs text-text-subtle">Token expires {{ $tokenExpiry->diffForHumans() }}</p>
                @endif
                <p class="text-xs text-text-subtle">
                    Re-authorize to refresh tokens or link new vehicles.
                </p>
                <a href="{{ route('tesla.redirect') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-sm font-medium text-text-secondary hover:bg-elevated hover:text-text-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Re-authorize Tesla Account
                </a>

                <div class="rounded-lg border border-border-input bg-surface-alt p-4">
                    <p class="mb-2 text-xs font-medium text-text-secondary">Key Pairing</p>
                    <p class="mb-2 text-xs text-text-subtle">
                        If your vehicle isn't receiving commands or telemetry, you may need to pair your public key.
                        Open this link on your phone near the vehicle:
                    </p>
                    <a href="https://tesla.com/_ak/{{ parse_url(config('app.url'), PHP_URL_HOST) }}"
                       target="_blank"
                       class="text-xs font-medium text-red-400 hover:text-red-300 break-all">
                        https://tesla.com/_ak/{{ parse_url(config('app.url'), PHP_URL_HOST) }}
                    </a>
                </div>
            </div>
        @else
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-gray-500"></span>
                    <span class="text-sm text-text-muted">Not connected</span>
                </div>
                <a href="{{ route('tesla.redirect') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Connect Tesla Account
                </a>
            </div>
        @endif
        </div>

        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-lg font-semibold">Advanced</h3>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" wire:model="debug_mode"
                    class="h-4 w-4 rounded border-border-input bg-surface-alt text-red-600 focus:ring-red-500">
                <div>
                    <span class="text-sm font-medium text-text-secondary">Debug Mode</span>
                    <p class="text-xs text-text-subtle">Show raw vehicle state data for troubleshooting</p>
                </div>
            </label>
        </div>

        <button type="submit" class="rounded-lg bg-red-600 px-6 py-2 font-medium text-white hover:bg-red-700">
            Save Settings
        </button>
    </form>

    {{-- Database Backup & Restore --}}
    @if(config('database.default') === 'sqlite')
    <div class="mt-6 max-w-2xl" x-data="{ showRestore: false, confirmRestore: false }">
        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-lg font-semibold">Database</h3>

            <div class="space-y-4">
                {{-- Backup --}}
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-text-secondary">Create Backup</p>
                        <p class="text-xs text-text-subtle">Download a copy of your database</p>
                    </div>
                    <a href="{{ route('settings.backup') }}"
                        class="rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-sm font-medium text-text-secondary hover:bg-elevated hover:text-text-primary">
                        Download Backup
                    </a>
                </div>

                {{-- Existing backups (server-side) --}}
                @if(count($this->backups) > 0)
                <div>
                    <p class="mb-2 text-sm font-medium text-text-secondary">Server Backups</p>
                    <div class="space-y-1">
                        @foreach($this->backups as $backup)
                        <div class="flex items-center justify-between rounded-lg bg-surface-alt px-3 py-2 text-sm">
                            <span class="text-text-secondary">{{ $backup['name'] }}</span>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-text-subtle">{{ $backup['size'] >= 1048576 ? round($backup['size'] / 1048576, 1) . ' MB' : round($backup['size'] / 1024, 1) . ' KB' }}</span>
                                <button wire:click="deleteBackup('{{ $backup['name'] }}')" wire:confirm="Delete this backup?"
                                    class="text-xs text-text-subtle hover:text-red-400">
                                    Delete
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Restore --}}
                <div class="border-t border-border-default pt-4"
                     x-data="{
                         file: null,
                         uploading: false,
                         confirmed: false,
                         progress: 0,
                         error: '',
                         chunkSize: 90 * 1024 * 1024,
                         sendChunk(url, form, token, onProgress) {
                             return new Promise((resolve, reject) => {
                                 const xhr = new XMLHttpRequest();
                                 xhr.open('POST', url);
                                 xhr.setRequestHeader('X-CSRF-TOKEN', token);
                                 xhr.setRequestHeader('Accept', 'application/json');
                                 if (onProgress) {
                                     xhr.upload.onprogress = function(e) {
                                         if (e.lengthComputable) onProgress(e.loaded, e.total);
                                     };
                                 }
                                 xhr.onload = function() {
                                     let data = {};
                                     try { data = JSON.parse(xhr.responseText); } catch (_) {}
                                     if (xhr.status >= 200 && xhr.status < 300) {
                                         resolve(data);
                                     } else {
                                         let msg = data.error || data.message || 'HTTP ' + xhr.status;
                                         if (data.errors) msg += ': ' + Object.values(data.errors).flat().join(', ');
                                         reject(new Error(msg));
                                     }
                                 };
                                 xhr.onerror = function() { reject(new Error('Network request failed')); };
                                 xhr.send(form);
                             });
                         },
                         async restore() {
                             if (!this.file || !this.confirmed) return;
                             this.uploading = true;
                             this.error = '';
                             this.progress = 0;

                             const totalChunks = Math.ceil(this.file.size / this.chunkSize);
                             const uploadId = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
                             const compressed = this.file.name.endsWith('.gz');
                             const token = document.querySelector('meta[name=csrf-token]').content;

                             for (let i = 0; i < totalChunks; i++) {
                                 const start = i * this.chunkSize;
                                 const end = Math.min(start + this.chunkSize, this.file.size);
                                 const blob = this.file.slice(start, end);

                                 const form = new FormData();
                                 form.append('chunk', blob, 'chunk.bin');
                                 form.append('chunk_index', String(i));
                                 form.append('total_chunks', String(totalChunks));
                                 form.append('upload_id', uploadId);
                                 if (i === totalChunks - 1) form.append('compressed', compressed ? '1' : '0');

                                 try {
                                     const self = this;
                                     const data = await this.sendChunk('/settings/restore/chunk', form, token, function(loaded, total) {
                                         const chunkProgress = loaded / total;
                                         self.progress = Math.round(((i + chunkProgress) / totalChunks) * 100);
                                     });
                                     this.progress = Math.round(((i + 1) / totalChunks) * 100);

                                     if (data.status === 'restored') {
                                         window.location.href = '/login';
                                         return;
                                     }
                                 } catch (e) {
                                     this.error = 'Chunk ' + (i + 1) + '/' + totalChunks + ' failed: ' + e.message;
                                     this.uploading = false;
                                     return;
                                 }
                             }
                         }
                     }">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-text-secondary">Restore from Backup</p>
                            <p class="text-xs text-text-subtle">Upload a .sqlite or .sqlite.gz backup file</p>
                        </div>
                        <button x-on:click="showRestore = !showRestore; file = null; confirmed = false; error = ''"
                            class="rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-sm font-medium text-text-secondary hover:bg-elevated hover:text-text-primary">
                            <span x-text="showRestore ? 'Cancel' : 'Restore'"></span>
                        </button>
                    </div>

                    <div x-show="showRestore" x-cloak class="mt-4 space-y-3">
                        <template x-if="error">
                            <div class="rounded-lg border border-red-800 bg-red-900/30 p-3 text-sm text-red-300" x-text="error"></div>
                        </template>

                        <div class="rounded-lg border border-border-input bg-surface-alt p-4">
                            <input type="file" accept=".sqlite,.gz"
                                x-on:change="file = $event.target.files[0]; confirmed = false; error = ''"
                                class="block w-full text-sm text-text-secondary file:mr-4 file:rounded-lg file:border-0 file:bg-elevated file:px-4 file:py-2 file:text-sm file:font-medium file:text-text-primary hover:file:bg-elevated/80">
                        </div>

                        <template x-if="file && !uploading">
                            <div class="space-y-3">
                                <div class="rounded-lg border border-yellow-800 bg-yellow-900/30 p-3 text-sm text-yellow-300">
                                    <strong>Warning:</strong> Restoring will replace your entire database. This cannot be undone. You will be logged out after restore.
                                </div>
                                <div>
                                    <button x-show="!confirmed" x-on:click="confirmed = true" type="button"
                                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                        I Understand, Restore
                                    </button>
                                    <button x-show="confirmed" x-cloak x-on:click="restore()" type="button"
                                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                        Confirm Restore
                                    </button>
                                </div>
                            </div>
                        </template>

                        <template x-if="uploading">
                            <div class="space-y-2">
                                <div class="h-2 overflow-hidden rounded-full bg-surface-alt">
                                    <div class="h-full rounded-full bg-red-600 transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                                </div>
                                <p class="text-sm text-text-muted">Uploading... <span x-text="progress"></span>%</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Password Modal --}}
    <div x-data="{ open: false, success: false }"
         x-on:open-password-modal.window="open = true; success = false"
         x-on:password-changed.window="success = true"
         x-on:close-password-modal.window="open = false"
         x-effect="if (!open) { $wire.set('current_password', ''); $wire.set('new_password', ''); $wire.set('new_password_confirmation', ''); $wire.set('passwordSaved', false); }"
         x-show="open"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center">

        {{-- Backdrop --}}
        <div x-show="open" x-transition.opacity x-on:click="open = false"
             class="fixed inset-0 bg-overlay"></div>

        {{-- Modal --}}
        <div x-show="open" x-transition
             class="relative z-10 w-full max-w-md rounded-xl border border-border-input bg-surface p-6 shadow-2xl">

            {{-- Success state --}}
            <template x-if="success">
                <div class="text-center py-4">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-500/10">
                        <svg class="h-7 w-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold">Password Changed</h3>
                    <p class="mt-1 text-sm text-text-muted">Your password has been updated successfully.</p>
                    <button type="button" x-on:click="open = false"
                        class="mt-6 rounded-lg bg-red-600 px-6 py-2 text-sm font-medium text-white hover:bg-red-700">
                        Done
                    </button>
                </div>
            </template>

            {{-- Form state --}}
            <template x-if="!success">
                <div>
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Change Password</h3>
                        <button x-on:click="open = false" class="text-text-muted hover:text-text-primary">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form wire:submit="changePassword" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary">Current Password</label>
                            <input type="password" wire:model="current_password"
                                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                            @error('current_password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary">New Password</label>
                            <input type="password" wire:model="new_password"
                                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                            @error('new_password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary">Confirm New Password</label>
                            <input type="password" wire:model="new_password_confirmation"
                                class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" x-on:click="open = false"
                                class="rounded-lg border border-border-input px-4 py-2 text-sm font-medium text-text-secondary hover:bg-surface-alt">
                                Cancel
                            </button>
                            <button type="submit"
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </div>
</div>
