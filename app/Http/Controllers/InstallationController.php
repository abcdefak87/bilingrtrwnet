<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\User;
use App\Services\ServiceProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstallationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ServiceProvisioningService $provisioningService
    ) {
    }

    /**
     * Display a listing of customers pending installation workflow.
     */
    public function index(Request $request)
    {
        $query = Customer::with(['services.package']);

        // Filter by installation status
        $status = $request->input('status', 'pending_survey');
        
        if ($status) {
            $query->where('status', $status);
        }

        $customers = $query->orderBy('created_at', 'asc')->paginate(15);

        // Get technicians for assignment dropdown
        $technicians = User::where('role', 'technician')->get();

        return view('admin.installations.index', compact('customers', 'technicians', 'status'));
    }

    /**
     * Assign a technician to a customer for survey.
     * Updates status from pending_survey to survey_scheduled.
     */
    public function assignTechnician(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Verify the user is actually a technician
        $technician = User::findOrFail($validated['technician_id']);
        
        if ($technician->role !== 'technician') {
            return back()->with('error', 'User yang dipilih bukan teknisi.');
        }

        // Verify customer is in pending_survey status
        if ($customer->status !== 'pending_survey') {
            return back()->with('error', 'Pelanggan tidak dalam status pending survey.');
        }

        DB::beginTransaction();
        try {
            // Update customer status to survey_scheduled
            $customer->update([
                'status' => 'survey_scheduled',
            ]);

            // Store technician assignment in customer metadata or create a separate assignment record
            // For now, we'll log this action
            Log::info('Technician assigned to customer', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'technician_id' => $technician->id,
                'technician_name' => $technician->name,
                'notes' => $validated['notes'] ?? null,
            ]);

            // TODO: Queue WhatsApp notification to technician
            // NotificationService::queueWhatsApp($technician->phone, "Anda ditugaskan untuk survey pelanggan: {$customer->name}");

            DB::commit();

            return back()->with('success', "Teknisi {$technician->name} berhasil ditugaskan untuk survey pelanggan {$customer->name}.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign technician', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->with('error', 'Gagal menugaskan teknisi. Silakan coba lagi.');
        }
    }

    /**
     * Update installation status (for technician use).
     * Allows technician to mark survey as complete.
     */
    public function updateStatus(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:survey_complete'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Verify customer is in survey_scheduled status
        if ($customer->status !== 'survey_scheduled') {
            return back()->with('error', 'Pelanggan tidak dalam status survey scheduled.');
        }

        DB::beginTransaction();
        try {
            // Update customer status to survey_complete
            $customer->update([
                'status' => $validated['status'],
            ]);

            Log::info('Installation status updated', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'new_status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            // TODO: Queue notification to admin for approval
            // NotificationService::notifyAdmin("Survey selesai untuk pelanggan: {$customer->name}. Menunggu approval.");

            DB::commit();

            return back()->with('success', 'Status instalasi berhasil diperbarui. Menunggu approval admin.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update installation status', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->with('error', 'Gagal memperbarui status. Silakan coba lagi.');
        }
    }

    /**
     * Show the approval form for a customer whose survey is complete.
     */
    public function showApproval(Customer $customer)
    {
        // Verify customer is in survey_complete status
        if ($customer->status !== 'survey_complete') {
            return redirect()->route('admin.installations.index')
                ->with('error', 'Pelanggan tidak dalam status survey complete.');
        }

        // Load customer with related data
        $customer->load(['services']);

        // Get available packages and routers for provisioning
        $packages = Package::where('is_active', true)->get();
        $routers = MikrotikRouter::where('is_active', true)->get();

        return view('admin.installations.approval', compact('customer', 'packages', 'routers'));
    }

    /**
     * Approve installation for a customer.
     * This will trigger service provisioning.
     */
    public function approve(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'mikrotik_id' => ['required', 'exists:mikrotik_routers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Verify customer is in survey_complete status
        if ($customer->status !== 'survey_complete') {
            return back()->with('error', 'Pelanggan tidak dalam status survey complete.');
        }

        // Verify package is active
        $package = Package::findOrFail($validated['package_id']);
        if (! $package->is_active) {
            return back()->with('error', 'Paket yang dipilih tidak aktif.');
        }

        // Verify router is active
        $router = MikrotikRouter::findOrFail($validated['mikrotik_id']);
        if (! $router->is_active) {
            return back()->with('error', 'Router yang dipilih tidak aktif.');
        }

        DB::beginTransaction();
        try {
            // Update customer status to approved
            $customer->update([
                'status' => 'approved',
            ]);

            Log::info('Installation approved', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'router_id' => $router->id,
                'router_name' => $router->name,
                'approved_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Provision service
            $result = $this->provisioningService->provisionService($customer, $package, $router);

            if ($result['success']) {
                // Service provisioned successfully
                $customer->update([
                    'status' => 'active',
                ]);

                DB::commit();

                // TODO: Queue WhatsApp notification with credentials
                // NotificationService::queueWhatsApp(
                //     $customer->phone,
                //     "Layanan Anda telah aktif!\n\nUsername: {$result['credentials']['username']}\nPassword: {$result['credentials']['password']}\n\nTerima kasih telah berlangganan."
                // );

                Log::info('Service activation notification queued', [
                    'customer_id' => $customer->id,
                    'service_id' => $result['service']->id,
                ]);

                return redirect()->route('admin.installations.index')
                    ->with('success', "Instalasi untuk pelanggan {$customer->name} telah disetujui dan layanan berhasil diaktifkan. Kredensial PPPoE: {$result['credentials']['username']}");
            } else {
                // Provisioning failed
                DB::commit(); // Commit the customer status change and service record

                Log::warning('Service provisioning failed, marked as provisioning_failed', [
                    'customer_id' => $customer->id,
                    'service_id' => $result['service']->id,
                ]);

                return redirect()->route('admin.installations.index')
                    ->with('warning', "Instalasi untuk pelanggan {$customer->name} telah disetujui, tetapi provisioning ke Mikrotik gagal. Service ID: {$result['service']->id} ditandai sebagai 'provisioning_failed'. Silakan coba manual provisioning.");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve installation', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->with('error', 'Gagal menyetujui instalasi. Silakan coba lagi. Error: '.$e->getMessage());
        }
    }

    /**
     * Reject installation for a customer.
     */
    public function reject(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        // Verify customer is in survey_complete status
        if ($customer->status !== 'survey_complete') {
            return back()->with('error', 'Pelanggan tidak dalam status survey complete.');
        }

        DB::beginTransaction();
        try {
            // Update customer status back to pending_survey for re-evaluation
            $customer->update([
                'status' => 'pending_survey',
            ]);

            Log::info('Installation rejected', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'rejected_by' => auth()->id(),
                'reason' => $validated['reason'],
            ]);

            // TODO: Queue WhatsApp notification to customer
            // NotificationService::queueWhatsApp($customer->phone, "Mohon maaf, instalasi Anda ditolak. Alasan: {$validated['reason']}");

            DB::commit();

            return redirect()->route('admin.installations.index')
                ->with('success', "Instalasi untuk pelanggan {$customer->name} telah ditolak.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject installation', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->with('error', 'Gagal menolak instalasi. Silakan coba lagi.');
        }
    }
}
