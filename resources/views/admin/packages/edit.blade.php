<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Paket') }}
            </h2>
            <a href="{{ route('admin.packages.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('Kembali') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.packages.update', $package) }}" id="packageForm">
                        @csrf
                        @method('PUT')

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nama Paket')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $package->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Speed -->
                        <div class="mb-4">
                            <x-input-label for="speed" :value="__('Kecepatan')" />
                            <x-text-input id="speed" class="block mt-1 w-full" type="text" name="speed" :value="old('speed', $package->speed)" required placeholder="Contoh: 10 Mbps, 20/10 Mbps" />
                            <p class="mt-1 text-sm text-gray-500">Format: Download atau Download/Upload (contoh: 10 Mbps atau 20/10 Mbps)</p>
                            <x-input-error :messages="$errors->get('speed')" class="mt-2" />
                        </div>

                        <!-- Price -->
                        <div class="mb-4">
                            <x-input-label for="price" :value="__('Harga (Rp)')" />
                            <x-text-input id="price" class="block mt-1 w-full" type="number" name="price" :value="old('price', $package->price)" required min="0" step="0.01" />
                            <x-input-error :messages="$errors->get('price')" class="mt-2" />
                        </div>

                        <!-- Type -->
                        <div class="mb-4">
                            <x-input-label for="type" :value="__('Tipe Paket')" />
                            <select id="type" name="type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                <option value="">Pilih Tipe</option>
                                <option value="unlimited" {{ old('type', $package->type) == 'unlimited' ? 'selected' : '' }}>Unlimited</option>
                                <option value="fup" {{ old('type', $package->type) == 'fup' ? 'selected' : '' }}>FUP (Fair Usage Policy)</option>
                                <option value="quota" {{ old('type', $package->type) == 'quota' ? 'selected' : '' }}>Quota</option>
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <!-- FUP Configuration (shown only when type is 'fup') -->
                        <div id="fupConfig" class="mb-4 p-4 bg-gray-50 rounded-md" style="display: none;">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Konfigurasi FUP</h3>
                            
                            <div class="mb-3">
                                <x-input-label for="fup_threshold" :value="__('Threshold FUP (GB)')" />
                                <x-text-input id="fup_threshold" class="block mt-1 w-full" type="number" name="fup_threshold" :value="old('fup_threshold', $package->fup_threshold)" min="0" placeholder="Contoh: 100" />
                                <p class="mt-1 text-sm text-gray-500">Batas kuota sebelum kecepatan dikurangi (dalam GB)</p>
                                <x-input-error :messages="$errors->get('fup_threshold')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="fup_speed" :value="__('Kecepatan Setelah FUP')" />
                                <x-text-input id="fup_speed" class="block mt-1 w-full" type="text" name="fup_speed" :value="old('fup_speed', $package->fup_speed)" placeholder="Contoh: 2 Mbps" />
                                <p class="mt-1 text-sm text-gray-500">Kecepatan setelah threshold tercapai</p>
                                <x-input-error :messages="$errors->get('fup_speed')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Is Active -->
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-600">Paket Aktif</span>
                            </label>
                            <p class="mt-1 text-sm text-gray-500">Hanya paket aktif yang dapat dipilih oleh pelanggan</p>
                            <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                        </div>

                        @if($package->services()->count() > 0)
                            <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                                <p class="text-sm text-yellow-800">
                                    <strong>Perhatian:</strong> Paket ini memiliki {{ $package->services()->count() }} layanan aktif. 
                                    Perubahan pada paket tidak akan mempengaruhi layanan yang sudah ada.
                                </p>
                            </div>
                        @endif

                        <div class="flex items-center justify-end mt-6">
                            <x-primary-button class="ml-3">
                                {{ __('Update Paket') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type');
            const fupConfig = document.getElementById('fupConfig');
            const fupThreshold = document.getElementById('fup_threshold');
            const fupSpeed = document.getElementById('fup_speed');

            function toggleFupConfig() {
                if (typeSelect.value === 'fup') {
                    fupConfig.style.display = 'block';
                    fupThreshold.required = true;
                    fupSpeed.required = true;
                } else {
                    fupConfig.style.display = 'none';
                    fupThreshold.required = false;
                    fupSpeed.required = false;
                }
            }

            typeSelect.addEventListener('change', toggleFupConfig);
            
            // Initialize on page load
            toggleFupConfig();
        });
    </script>
</x-app-layout>
