<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');

        if (app()->environment('local')) {
            $designer = User::firstOrCreate(
                ['email' => 'designer@example.com'],
                [
                    'name' => 'Demo Designer',
                    'password' => 'password',
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'supervisor_id' => $admin->id,
                ]
            );
            $designer->assignRole('workflow-designer');

            $employee = User::firstOrCreate(
                ['email' => 'employee@example.com'],
                [
                    'name' => 'Demo Mitarbeiter',
                    'password' => 'password',
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'supervisor_id' => $designer->id,
                ]
            );
            $employee->assignRole('employee');
        }
    }
}
