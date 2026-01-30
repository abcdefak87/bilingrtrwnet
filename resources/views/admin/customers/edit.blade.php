<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Pelanggan: ') }} {{ $customer->name }}
            </h2>
            <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Nama Lengkap')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $customer->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Phone -->
                        <div>
                            <x-input-label for="phone" :value="__('Nomor Telepon')" />
                            <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone', $customer->phone)" required />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <!-- KTP Number -->
                        <div>
                            <x-input-label for="ktp_number" :value="__('Nomor KTP')" />
                            <x-text-input id="ktp_number" class="block mt-1 w-full" type="text" name="ktp_number" :value="old('ktp_number', $customer->ktp_number)" required />
                            <x-input-error :messages="$errors->get('ktp_number')" class="mt-2" />
                        </div>

                        <!-- Address -->
                        <div>
                            <x-input-label for="address" :value="__('Alamat Lengkap')" />
                            <textarea id="address" name="address" rows="3" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>{{ old('address', $customer->address) }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <!-- Status -->
                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                <option value="pending_survey" {{ old('status', $customer->status) == 'pending_survey' ? 'selected' : '' }}>Pending Survey</option>
                                <option value="survey_scheduled" {{ old('status', $customer->status) == 'survey_scheduled' ? 'selected' : '' }}>Survey Dijadwalkan</option>
                                <option value="survey_complete" {{ old('status', $customer->status) == 'survey_complete' ? 'selected' : '' }}>Survey Selesai</option>
                                <option value="approved" {{ old('status', $customer->status) == 'approved' ? 'selected' : '' }}>Disetujui</option>
                                <option value="active" {{ old('status', $customer->status) == 'active' ? 'selected' : '' }}>Aktif</option>
                                <option value="suspended" {{ old('status', $customer->status) == 'suspended' ? 'selected' : '' }}>Ditangguhkan</option>
                                <option value="terminated" {{ old('status', $customer->status) == 'terminated' ? 'selected' : '' }}>Dihentikan</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <!-- Coordinates -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="latitude" :value="__('Latitude (Opsional)')" />
                                <x-text-input id="latitude" class="block mt-1 w-full" type="text" name="latitude" :value="old('latitude', $customer->latitude)" placeholder="-6.200000" />
                                <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="longitude" :value="__('Longitude (Opsional)')" />
                                <x-text-input id="longitude" class="block mt-1 w-full" type="text" name="longitude" :value="old('longitude', $customer->longitude)" placeholder="106.816666" />
                                <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                            </div>
                        </div>

                        <!-- KTP File -->
                        <div>
                            <x-input-label for="ktp_file" :value="__('Foto KTP (Opsional - hanya jika ingin mengganti)')" />
                            @if($customer->ktp_path)
                                <div class="mt-2 mb-2">
                                    <p class="text-sm text-gray-600">File saat ini: 
                                        <a href="{{ asset('storage/' . $customer->ktp_path) }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                            Lihat Foto KTP
                                        </a>
                                    </p>
                                </div>
                            @endif
                            <input id="ktp_file" type="file" name="ktp_file" accept=".jpg,.jpeg,.png,.pdf" class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            <p class="mt-1 text-sm text-gray-500">Format: JPG, PNG, atau PDF. Maksimal 2MB.</p>
                            <x-input-error :messages="$errors->get('ktp_file')" class="mt-2" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Batal
                            </a>
                            <x-primary-button>
                                {{ __('Simpan Perubahan') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
