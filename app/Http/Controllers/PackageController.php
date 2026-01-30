<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    /**
     * Display a public listing of active packages.
     */
    public function publicIndex()
    {
        $packages = Package::where('is_active', true)
            ->orderBy('price', 'asc')
            ->get();

        return view('packages.index', compact('packages'));
    }

    /**
     * Display the specified package details for public view.
     */
    public function publicShow(Package $package)
    {
        // Only show active packages to public
        if (!$package->is_active) {
            abort(404);
        }

        return view('packages.show', compact('package'));
    }

    /**
     * Display a listing of packages.
     */
    public function index()
    {
        $packages = Package::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('speed', 'like', "%{$search}%");
            })
            ->when(request('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when(request('status') !== null, function ($query) {
                $status = request('status') === '1';
                $query->where('is_active', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.packages.index', compact('packages'));
    }

    /**
     * Show the form for creating a new package.
     */
    public function create()
    {
        return view('admin.packages.create');
    }

    /**
     * Store a newly created package in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'speed' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'type' => ['required', Rule::in(['unlimited', 'fup', 'quota'])],
            'fup_threshold' => ['nullable', 'integer', 'min:0'],
            'fup_speed' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        // Validate FUP fields if type is 'fup'
        if ($validated['type'] === 'fup') {
            $request->validate([
                'fup_threshold' => ['required', 'integer', 'min:1'],
                'fup_speed' => ['required', 'string', 'max:100'],
            ]);
        }

        // Set default is_active to true if not provided
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->is_active : true;

        Package::create($validated);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Paket berhasil dibuat.');
    }

    /**
     * Display the specified package.
     */
    public function show(Package $package)
    {
        $package->load(['services' => function ($query) {
            $query->latest()->take(10);
        }]);

        return view('admin.packages.show', compact('package'));
    }

    /**
     * Show the form for editing the specified package.
     */
    public function edit(Package $package)
    {
        return view('admin.packages.edit', compact('package'));
    }

    /**
     * Update the specified package in storage.
     */
    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'speed' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'type' => ['required', Rule::in(['unlimited', 'fup', 'quota'])],
            'fup_threshold' => ['nullable', 'integer', 'min:0'],
            'fup_speed' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        // Validate FUP fields if type is 'fup'
        if ($validated['type'] === 'fup') {
            $request->validate([
                'fup_threshold' => ['required', 'integer', 'min:1'],
                'fup_speed' => ['required', 'string', 'max:100'],
            ]);
        }

        // Handle is_active checkbox
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->is_active : false;

        $package->update($validated);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Paket berhasil diperbarui.');
    }

    /**
     * Remove the specified package from storage.
     */
    public function destroy(Package $package)
    {
        // Check if package has any services (active or not)
        if ($package->services()->exists()) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Tidak dapat menghapus paket yang memiliki layanan terkait. Hapus atau ubah layanan terlebih dahulu.');
        }

        $package->delete();

        return redirect()->route('admin.packages.index')
            ->with('success', 'Paket berhasil dihapus.');
    }
}
