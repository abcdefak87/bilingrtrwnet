<x-guest-layout>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h2 class="text-2xl font-bold mb-6">Pendaftaran Pelanggan Baru</h2>
                    
                    <form method="POST" action="{{ route('customer.register.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Nama Lengkap')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Phone -->
                        <div>
                            <x-input-label for="phone" :value="__('Nomor Telepon')" />
                            <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone')" required placeholder="08xxxxxxxxxx" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            <p class="mt-1 text-sm text-gray-600">Nomor WhatsApp aktif untuk notifikasi</p>
                        </div>

                        <!-- Address -->
                        <div>
                            <x-input-label for="address" :value="__('Alamat Lengkap')" />
                            <textarea id="address" name="address" rows="3" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>{{ old('address') }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                            <p class="mt-1 text-sm text-gray-600">Alamat lengkap untuk instalasi</p>
                        </div>

                        <!-- KTP Number -->
                        <div>
                            <x-input-label for="ktp_number" :value="__('Nomor KTP')" />
                            <x-text-input id="ktp_number" class="block mt-1 w-full" type="text" name="ktp_number" :value="old('ktp_number')" required maxlength="20" />
                            <x-input-error :messages="$errors->get('ktp_number')" class="mt-2" />
                        </div>

                        <!-- KTP File Upload -->
                        <div>
                            <x-input-label for="ktp_file" :value="__('Foto KTP')" />
                            <input id="ktp_file" type="file" name="ktp_file" accept=".jpg,.jpeg,.png,.pdf" required class="block mt-1 w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" />
                            <x-input-error :messages="$errors->get('ktp_file')" class="mt-2" />
                            <p class="mt-1 text-sm text-gray-600">Format: JPG, PNG, atau PDF. Maksimal 2MB</p>
                        </div>

                        <!-- Package Selection -->
                        <div>
                            <x-input-label for="package_id" :value="__('Pilih Paket')" />
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($packages as $package)
                                    @php
                                        $isSelected = old('package_id', request('package_id')) == $package->id;
                                    @endphp
                                    <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none {{ $isSelected ? 'border-indigo-600 ring-2 ring-indigo-600' : 'border-gray-300' }}">
                                        <input type="radio" name="package_id" value="{{ $package->id }}" class="sr-only" {{ $isSelected ? 'checked' : '' }} required />
                                        <span class="flex flex-1">
                                            <span class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900">{{ $package->name }}</span>
                                                <span class="mt-1 flex items-center text-sm text-gray-500">
                                                    Kecepatan: {{ $package->speed }}
                                                </span>
                                                <span class="mt-1 text-lg font-bold text-indigo-600">
                                                    Rp {{ number_format($package->price, 0, ',', '.') }}/bulan
                                                </span>
                                                @if($package->type === 'fup' && $package->fup_threshold)
                                                    <span class="mt-1 text-xs text-gray-500">
                                                        FUP: {{ $package->fup_threshold }}GB → {{ $package->fup_speed }}
                                                    </span>
                                                @endif
                                            </span>
                                        </span>
                                        <svg class="h-5 w-5 text-indigo-600 {{ $isSelected ? '' : 'invisible' }}" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                        </svg>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('package_id')" class="mt-2" />
                        </div>

                        <!-- Optional: Location Coordinates -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="latitude" :value="__('Latitude (Opsional)')" />
                                <x-text-input id="latitude" class="block mt-1 w-full" type="text" name="latitude" :value="old('latitude')" placeholder="-6.200000" />
                                <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="longitude" :value="__('Longitude (Opsional)')" />
                                <x-text-input id="longitude" class="block mt-1 w-full" type="text" name="longitude" :value="old('longitude')" placeholder="106.816666" />
                                <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-primary-button class="ml-4">
                                {{ __('Daftar Sekarang') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
