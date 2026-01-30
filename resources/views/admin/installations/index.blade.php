<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manajemen Instalasi') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Success/Error Messages -->
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Status Filter Tabs -->
                    <div class="mb-6 border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <a href="{{ route('admin.installations.index', ['status' => 'pending_survey']) }}" 
                               class="@if($status == 'pending_survey') border-blue-500 text-blue-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Pending Survey
                            </a>
                            <a href="{{ route('admin.installations.index', ['status' => 'survey_scheduled']) }}" 
                               class="@if($status == 'survey_scheduled') border-blue-500 text-blue-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Survey Scheduled
                            </a>
                            <a href="{{ route('admin.installations.index', ['status' => 'survey_complete']) }}" 
                               class="@if($status == 'survey_complete') border-blue-500 text-blue-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Survey Complete
                            </a>
                            <a href="{{ route('admin.installations.index', ['status' => 'approved']) }}" 
                               class="@if($status == 'approved') border-blue-500 text-blue-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Approved
                            </a>
                        </nav>
                    </div>

                    <!-- Customers Table -->
                    @if($customers->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Pelanggan
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Kontak
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Alamat
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tanggal Registrasi
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($customers as $customer)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $customer->name }}</div>
                                                <div class="text-sm text-gray-500">KTP: {{ $customer->ktp_number }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $customer->phone }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">{{ Str::limit($customer->address, 50) }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    @if($customer->status == 'approved') bg-green-100 text-green-800
                                                    @elseif($customer->status == 'survey_complete') bg-blue-100 text-blue-800
                                                    @elseif($customer->status == 'survey_scheduled') bg-yellow-100 text-yellow-800
                                                    @else bg-gray-100 text-gray-800
                                                    @endif">
                                                    {{ ucfirst(str_replace('_', ' ', $customer->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $customer->created_at->format('d M Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex gap-2">
                                                    <a href="{{ route('admin.customers.show', $customer) }}" 
                                                       class="text-blue-600 hover:text-blue-900">
                                                        Lihat
                                                    </a>
                                                    
                                                    @if($customer->status == 'pending_survey')
                                                        <button onclick="openAssignModal({{ $customer->id }}, '{{ $customer->name }}')" 
                                                                class="text-green-600 hover:text-green-900">
                                                            Tugaskan Teknisi
                                                        </button>
                                                    @endif
                                                    
                                                    @if($customer->status == 'survey_scheduled')
                                                        <button onclick="openStatusModal({{ $customer->id }}, '{{ $customer->name }}')" 
                                                                class="text-purple-600 hover:text-purple-900">
                                                            Update Status
                                                        </button>
                                                    @endif
                                                    
                                                    @if($customer->status == 'survey_complete')
                                                        <a href="{{ route('admin.installations.approval', $customer) }}" 
                                                           class="text-orange-600 hover:text-orange-900">
                                                            Review
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $customers->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500">Tidak ada pelanggan dengan status {{ str_replace('_', ' ', $status) }}.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Technician Modal -->
    <div id="assignModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Tugaskan Teknisi</h3>
                <form id="assignForm" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pelanggan</label>
                        <p id="assignCustomerName" class="text-sm text-gray-900"></p>
                    </div>
                    <div class="mb-4">
                        <label for="technician_id" class="block text-sm font-medium text-gray-700 mb-2">Pilih Teknisi</label>
                        <select name="technician_id" id="technician_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">-- Pilih Teknisi --</option>
                            @foreach($technicians as $technician)
                                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Catatan (Opsional)</label>
                        <textarea name="notes" id="notes" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" onclick="closeAssignModal()"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Tugaskan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Status Survey</h3>
                <form id="statusForm" method="POST">
                    @csrf
                    <input type="hidden" name="status" value="survey_complete">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pelanggan</label>
                        <p id="statusCustomerName" class="text-sm text-gray-900"></p>
                    </div>
                    <div class="mb-4">
                        <label for="status_notes" class="block text-sm font-medium text-gray-700 mb-2">Catatan Survey</label>
                        <textarea name="notes" id="status_notes" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                  placeholder="Hasil survey, kondisi lokasi, dll..."></textarea>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" onclick="closeStatusModal()"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Tandai Survey Selesai
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAssignModal(customerId, customerName) {
            document.getElementById('assignCustomerName').textContent = customerName;
            document.getElementById('assignForm').action = `/admin/installations/${customerId}/assign-technician`;
            document.getElementById('assignModal').classList.remove('hidden');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.add('hidden');
            document.getElementById('assignForm').reset();
        }

        function openStatusModal(customerId, customerName) {
            document.getElementById('statusCustomerName').textContent = customerName;
            document.getElementById('statusForm').action = `/admin/installations/${customerId}/update-status`;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
            document.getElementById('statusForm').reset();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const assignModal = document.getElementById('assignModal');
            const statusModal = document.getElementById('statusModal');
            if (event.target == assignModal) {
                closeAssignModal();
            }
            if (event.target == statusModal) {
                closeStatusModal();
            }
        }
    </script>
</x-app-layout>
