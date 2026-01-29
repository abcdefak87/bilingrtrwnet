<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@ispbilling.test',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);

        // Create Admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@ispbilling.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'tenant_id' => null,
        ]);

        // Create Technician
        User::create([
            'name' => 'Technician User',
            'email' => 'technician@ispbilling.test',
            'password' => Hash::make('password'),
            'role' => 'technician',
            'tenant_id' => null,
        ]);

        // Create Customer
        User::create([
            'name' => 'Customer User',
            'email' => 'customer@ispbilling.test',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'tenant_id' => null,
        ]);

        // Create Reseller (Tenant 1)
        User::create([
            'name' => 'Reseller User',
            'email' => 'reseller@ispbilling.test',
            'password' => Hash::make('password'),
            'role' => 'reseller',
            'tenant_id' => 1,
        ]);

        $this->command->info('Users with different roles created successfully!');
        $this->command->info('Super Admin: superadmin@ispbilling.test / password');
        $this->command->info('Admin: admin@ispbilling.test / password');
        $this->command->info('Technician: technician@ispbilling.test / password');
        $this->command->info('Customer: customer@ispbilling.test / password');
        $this->command->info('Reseller: reseller@ispbilling.test / password');
    }
}
