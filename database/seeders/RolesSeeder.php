<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         Role::firstOrCreate(['name' => 'trabajador',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'contratador',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

    }
}


