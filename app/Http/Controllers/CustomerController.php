<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Show the customer registration form.
     */
    public function create()
    {
        // Get all active packages for display
        $packages = Package::where('is_active', true)->get();
        
        return view('customers.register', compact('packages'));
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(Request $request)
    {
        // Validate the registration data
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:customers,phone'],
            'address' => ['required', 'string'],
            'ktp_number' => ['required', 'string', 'max:20', 'unique:customers,ktp_number'],
            'ktp_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'], // 2MB max
            'package_id' => ['required', 'exists:packages,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.unique' => 'Nomor telepon sudah terdaftar.',
            'address.required' => 'Alamat lengkap wajib diisi.',
            'ktp_number.required' => 'Nomor KTP wajib diisi.',
            'ktp_number.unique' => 'Nomor KTP sudah terdaftar.',
            'ktp_file.required' => 'Foto KTP wajib diunggah.',
            'ktp_file.mimes' => 'Foto KTP harus berformat JPG, PNG, atau PDF.',
            'ktp_file.max' => 'Ukuran foto KTP maksimal 2MB.',
            'package_id.required' => 'Paket layanan wajib dipilih.',
            'package_id.exists' => 'Paket layanan tidak valid.',
        ]);

        // Handle KTP file upload
        $ktpPath = null;
        if ($request->hasFile('ktp_file')) {
            $ktpPath = $request->file('ktp_file')->store('ktp', 'public');
        }

        // Create customer record with status "pending_survey"
        $customer = Customer::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'ktp_number' => $validated['ktp_number'],
            'ktp_path' => $ktpPath,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => 'pending_survey',
            'tenant_id' => null, // Will be set by admin if multi-tenancy is enabled
        ]);

        // Store selected package in session for later use
        session(['customer_package_id' => $validated['package_id']]);

        // TODO: Queue notification job to send WhatsApp confirmation to customer
        // TODO: Queue notification job to notify admin via dashboard

        return redirect()->route('customer.registration.success')
            ->with('success', 'Pendaftaran berhasil! Tim kami akan menghubungi Anda untuk jadwal survey.');
    }

    /**
     * Show the registration success page.
     */
    public function success()
    {
        return view('customers.registration-success');
    }

    /**
     * Display a listing of customers for admin.
     */
    public function index(Request $request)
    {
        $query = Customer::with(['services.package', 'tickets']);

        // Search functionality across multiple fields
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('ktp_number', 'like', "%{$search}%")
                  ->orWhereHas('services', function ($serviceQuery) use ($search) {
                      $serviceQuery->where('username_pppoe', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status if provided
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Order by most recent first
        $customers = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.customers.index', compact('customers'));
    }

    /**
     * Display the specified customer profile with related data.
     */
    public function show(Customer $customer)
    {
        // Eager load all related data for the profile view
        $customer->load([
            'services.package',
            'services.mikrotikRouter',
            'services.invoices.payments',
            'tickets.assignedTo'
        ]);

        // Get summary statistics
        $stats = [
            'total_services' => $customer->services->count(),
            'active_services' => $customer->services->where('status', 'active')->count(),
            'total_invoices' => $customer->services->sum(fn($service) => $service->invoices->count()),
            'unpaid_invoices' => $customer->services->sum(fn($service) => 
                $service->invoices->where('status', 'unpaid')->count()
            ),
            'total_paid' => $customer->services->sum(fn($service) => 
                $service->invoices->where('status', 'paid')->sum('amount')
            ),
            'open_tickets' => $customer->tickets->whereIn('status', ['open', 'in_progress'])->count(),
        ];

        return view('admin.customers.show', compact('customer', 'stats'));
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer)
    {
        return view('admin.customers.edit', compact('customer'));
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('customers')->ignore($customer->id)],
            'address' => ['required', 'string'],
            'ktp_number' => ['required', 'string', 'max:20', Rule::unique('customers')->ignore($customer->id)],
            'ktp_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'status' => ['required', 'in:pending_survey,survey_scheduled,survey_complete,approved,active,suspended,terminated'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Handle KTP file upload if provided
        if ($request->hasFile('ktp_file')) {
            // Delete old file if exists
            if ($customer->ktp_path) {
                Storage::delete($customer->ktp_path);
            }
            $validated['ktp_path'] = $request->file('ktp_file')->store('ktp', 'public');
        }

        $customer->update($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer)
    {
        // Check if customer has active services
        if ($customer->services()->whereIn('status', ['active', 'pending'])->exists()) {
            return redirect()->route('admin.customers.index')
                ->with('error', 'Tidak dapat menghapus pelanggan dengan layanan aktif.');
        }

        // Delete KTP file if exists
        if ($customer->ktp_path) {
            Storage::delete($customer->ktp_path);
        }

        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', 'Pelanggan berhasil dihapus.');
    }
}
