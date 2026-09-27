<?php

namespace Database\Seeders;

use App\Models\WhatsAppNumber;
use Illuminate\Database\Seeder;

class WhatsAppNumberSeeder extends Seeder
{
    public function run(): void
    {
        WhatsAppNumber::query()->firstOrCreate(
            ['phone_number' => '+593981111111'],
            ['business_id' => 1, 'active' => true]
        );

        WhatsAppNumber::query()->firstOrCreate(
            ['phone_number' => '+593982222222'],
            ['business_id' => 2, 'active' => true]
        );
    }
}