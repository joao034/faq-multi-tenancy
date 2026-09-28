<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\ChatRequest;
use Illuminate\Database\Seeder;

class ChatRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $business = Business::query()->first();

        if ($business === null) {
            return;
        }

        ChatRequest::factory()
            ->for($business)
            ->count(3)
            ->create();
    }
}
