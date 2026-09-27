<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        Business::query()->firstOrCreate(
            ['id' => 1],
            ['name' => 'Café Montana', 'monthly_request_limit' => 300]
        );
 
        Business::query()->firstOrCreate(
            ['id' => 2],
            ['name' => 'Panadería Ketal', 'monthly_request_limit' => 500]
        );
    }
}
