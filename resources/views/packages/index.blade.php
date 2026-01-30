<x-guest-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Paket Internet Kami</h1>
                <p class="text-lg text-gray-600">Pilih paket internet yang sesuai dengan kebutuhan Anda</p>
            </div>

            <!-- Package Grid -->
            @if($packages->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500">
                        <p>Belum ada paket yang tersedia saat ini.</p>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($packages as $package)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition-shadow duration-300">
                            <div class="p-6">
                                <!-- Package Name -->
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $package->name }}</h3>
                                
                                <!-- Speed -->
                                <div class="flex items-center mb-4">
                                    <svg class="w-5 h-5 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span class="text-lg text-gray-700">{{ $package->speed }}</span>
                                </div>

                                <!-- Price -->
                                <div class="mb-4">
                                    <span class="text-3xl font-bold text-indigo-600">Rp {{ number_format($package->price, 0, ',', '.') }}</span>
                                    <span class="text-gray-600">/bulan</span>
                                </div>

                                <!-- Package Type Badge -->
                                <div class="mb-4">
                                    @if($package->type === 'unlimited')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            Unlimited
                                        </span>
                                    @elseif($package->type === 'fup')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                            FUP
                                        </span>
                                    @elseif($package->type === 'quota')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            Quota
                                        </span>
                                    @endif
                                </div>

                                <!-- FUP Details -->
                                @if($package->type === 'fup' && $package->fup_threshold)
                                    <div class="mb-4 p-3 bg-yellow-50 rounded-lg">
                                        <p class="text-sm text-gray-700">
                                            <span class="font-semibold">FUP:</span> {{ $package->fup_threshold }}GB
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Setelah FUP: {{ $package->fup_speed }}
                                        </p>
                                    </div>
                                @endif

                                <!-- Action Buttons -->
                                <div class="flex flex-col space-y-2">
                                    <a href="{{ route('packages.show', $package) }}" class="inline-flex justify-center items-center px-4 py-2 bg-white border border-indigo-600 rounded-md font-semibold text-sm text-indigo-600 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        Lihat Detail
                                    </a>
                                    <a href="{{ route('customer.register') }}?package_id={{ $package->id }}" class="inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        Daftar Sekarang
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Call to Action -->
            <div class="mt-12 text-center">
                <p class="text-gray-600 mb-4">Sudah memilih paket yang cocok?</p>
                <a href="{{ route('customer.register') }}" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Daftar Sekarang
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
