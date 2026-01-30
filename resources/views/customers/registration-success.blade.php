<x-guest-layout>
    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-center">
                    <!-- Success Icon -->
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                        <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Pendaftaran Berhasil!</h2>
                    
                    <div class="text-gray-600 space-y-3 mb-6">
                        <p>Terima kasih telah mendaftar sebagai pelanggan kami.</p>
                        <p>Tim kami akan segera menghubungi Anda melalui WhatsApp untuk menjadwalkan survey lokasi.</p>
                        <p class="text-sm">Proses survey diperlukan untuk memastikan kelayakan instalasi di lokasi Anda.</p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <h3 class="font-semibold text-blue-900 mb-2">Langkah Selanjutnya:</h3>
                        <ol class="text-left text-sm text-blue-800 space-y-2 list-decimal list-inside">
                            <li>Tunggu konfirmasi dari tim kami via WhatsApp</li>
                            <li>Jadwal survey akan ditentukan bersama</li>
                            <li>Teknisi akan melakukan survey lokasi</li>
                            <li>Setelah disetujui, instalasi akan dijadwalkan</li>
                            <li>Nikmati layanan internet berkecepatan tinggi!</li>
                        </ol>
                    </div>

                    <div class="flex justify-center space-x-4">
                        <a href="{{ route('customer.register') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                            Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
