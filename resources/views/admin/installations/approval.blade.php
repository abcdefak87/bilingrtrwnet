<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Review Instalasi: ') }} {{ $customer->name }}
            </h2>
            <a href="{{ route('admin.installations.index', ['status' => 'survey_complete']) }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400">
                Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Error Messages -->
            @if (session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Customer Information Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Informasi Pelanggan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Nama Lengkap</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $customer->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Nomor Telepon</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $customer->phone }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Nomor KTP</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $customer->ktp_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Status</p>
                            <p class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ ucfirst(str_replace('_', ' ', $customer->status)) }}
                                </span>
                            </p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-sm font-medium text-gray-500">Alamat</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $customer->address }}</p>
                        </div>
                        @if($customer->latitude && $customer->longitude)
                            <div>
                                <p class="text-sm font-medium text-gray-500">Koordinat</p>
                                <p class="mt-1 text-sm text-gray-900">{{ $customer->latitude }}, {{ $customer->longitude }}</p>
                            </div>
                        @endif
                        @if($customer->ktp_path)
                            <div>
                                <p class="text-sm font-medium text-gray-500">Foto KTP</p>
                                <a href="{{ asset('storage/' . $customer->ktp_path) }}" target="_blank" 
                                   class="mt-1 text-sm text-blue-600 hover:text-blue-800">
                                    Lihat Foto KTP
                                </a>
                            </div>
                        @endif
                        <div>
                            <p class="text-sm font-medium text-gray-500">Tanggal Registrasi</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $customer->created_at->format('d M Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Actions -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Keputusan Instalasi</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        Survey telah selesai dilakukan. Silakan review informasi pelanggan di atas dan putuskan untuk menyetujui atau menolak instalasi.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Approve Form -->
                        <div class="border border-green-300 rounded-lg p-4 bg-green-50">
                            <h4 class="font-semibold text-green-800 mb-3">Setujui Instalasi</h4>
                            <p class="text-sm text-gray-600 mb-4">
                                Menyetujui instalasi akan memulai proses provisioning layanan untuk pelanggan ini.
                            </p>
                            <form method="POST" action="{{ route('admin.installations.approve', $customer) }}">
                                @csrf
                                <div class="mb-4">
                                    <label for="package_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Paket Layanan <span class="text-red-500">*</span>
                                    </label>
                                    <select name="package_id" id="package_id" required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                        <option value="">Pilih Paket</option>
                                        @foreach($packages as $package)
                                            <option value="{{ $package->id }}">
                                                {{ $package->name }} - {{ $package->speed }} - Rp {{ number_format($package->price, 0, ',', '.') }}/bulan
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label for="mikrotik_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Router Mikrotik <span class="text-red-500">*</span>
                                    </label>
                                    <select name="mikrotik_id" id="mikrotik_id" required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                        <option value="">Pilih Router</option>
                                        @foreach($routers as $router)
                                            <option value="{{ $router->id }}">
                                                {{ $router->name }} ({{ $router->ip_address }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label for="approve_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                        Catatan (Opsional)
                                    </label>
                                    <textarea name="notes" id="approve_notes" rows="3"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                              placeholder="Catatan tambahan untuk approval..."></textarea>
                                </div>
                                <button type="submit"
                                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Setujui Instalasi
                                </button>
                            </form>
                        </div>

                        <!-- Reject Form -->
                        <div class="border border-red-300 rounded-lg p-4 bg-red-50">
                            <h4 class="font-semibold text-red-800 mb-3">Tolak Instalasi</h4>
                            <p class="text-sm text-gray-600 mb-4">
                                Menolak instalasi akan mengembalikan status pelanggan ke pending survey.
                            </p>
                            <form method="POST" action="{{ route('admin.installations.reject', $customer) }}">
                                @csrf
                                <div class="mb-4">
                                    <label for="reject_reason" class="block text-sm font-medium text-gray-700 mb-2">
                                        Alasan Penolakan <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="reason" id="reject_reason" rows="3" required
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                              placeholder="Jelaskan alasan penolakan instalasi..."></textarea>
                                </div>
                                <button type="submit"
                                        onclick="return confirm('Apakah Anda yakin ingin menolak instalasi ini?')"
                                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Tolak Instalasi
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Information Notice -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Informasi</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Setelah disetujui, sistem akan otomatis membuat kredensial PPPoE untuk pelanggan</li>
                                <li>Layanan akan diprovisi ke router Mikrotik yang ditugaskan</li>
                                <li>Pelanggan akan menerima notifikasi WhatsApp dengan detail login</li>
                                <li>Jika ditolak, pelanggan akan dikembalikan ke status pending survey untuk evaluasi ulang</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
