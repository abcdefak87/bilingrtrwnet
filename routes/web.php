<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Public customer registration routes
Route::get('/customer-registration', [CustomerController::class, 'create'])->name('customer.register');
Route::post('/customer-registration', [CustomerController::class, 'store'])->name('customer.register.store');
Route::get('/customer-registration-success', [CustomerController::class, 'success'])->name('customer.registration.success');

// Public package routes
Route::get('/packages', [\App\Http\Controllers\PackageController::class, 'publicIndex'])->name('packages.index');
Route::get('/packages/{package}', [\App\Http\Controllers\PackageController::class, 'publicShow'])->name('packages.show');

// Admin customer management routes
Route::middleware(['auth', 'permission:customers.view'])->prefix('admin')->group(function () {
    Route::get('/customers', [CustomerController::class, 'index'])->name('admin.customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('admin.customers.show');
    
    Route::middleware('permission:customers.update')->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('admin.customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('admin.customers.update');
    });
    
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('admin.customers.destroy')
        ->middleware('permission:customers.delete');
});

// Admin package management routes
Route::middleware(['auth', 'permission:packages.view'])->prefix('admin')->group(function () {
    Route::get('/packages', [\App\Http\Controllers\PackageController::class, 'index'])->name('admin.packages.index');
    
    Route::middleware('permission:packages.create')->group(function () {
        Route::get('/packages/create', [\App\Http\Controllers\PackageController::class, 'create'])->name('admin.packages.create');
        Route::post('/packages', [\App\Http\Controllers\PackageController::class, 'store'])->name('admin.packages.store');
    });
    
    Route::get('/packages/{package}', [\App\Http\Controllers\PackageController::class, 'show'])->name('admin.packages.show');
    
    Route::middleware('permission:packages.update')->group(function () {
        Route::get('/packages/{package}/edit', [\App\Http\Controllers\PackageController::class, 'edit'])->name('admin.packages.edit');
        Route::put('/packages/{package}', [\App\Http\Controllers\PackageController::class, 'update'])->name('admin.packages.update');
    });
    
    Route::delete('/packages/{package}', [\App\Http\Controllers\PackageController::class, 'destroy'])
        ->name('admin.packages.destroy')
        ->middleware('permission:packages.delete');
});

// Admin installation workflow routes
Route::middleware(['auth', 'permission:customers.view'])->prefix('admin')->group(function () {
    Route::get('/installations', [\App\Http\Controllers\InstallationController::class, 'index'])->name('admin.installations.index');
    
    Route::middleware('permission:customers.update')->group(function () {
        Route::post('/installations/{customer}/assign-technician', [\App\Http\Controllers\InstallationController::class, 'assignTechnician'])
            ->name('admin.installations.assign-technician');
        Route::post('/installations/{customer}/update-status', [\App\Http\Controllers\InstallationController::class, 'updateStatus'])
            ->name('admin.installations.update-status');
        Route::get('/installations/{customer}/approval', [\App\Http\Controllers\InstallationController::class, 'showApproval'])
            ->name('admin.installations.approval');
        Route::post('/installations/{customer}/approve', [\App\Http\Controllers\InstallationController::class, 'approve'])
            ->name('admin.installations.approve');
        Route::post('/installations/{customer}/reject', [\App\Http\Controllers\InstallationController::class, 'reject'])
            ->name('admin.installations.reject');
    });
});

// Payment webhook routes (excluded from CSRF protection)
Route::post('/webhooks/payment/{gateway}', [\App\Http\Controllers\PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payment')
    ->whereIn('gateway', ['midtrans', 'xendit', 'tripay']);

require __DIR__.'/auth.php';
