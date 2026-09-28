<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ChatRequest;

class UsageService
{
    public function startRequest(Business $business, string $from, string $message): ?ChatRequest
    {
        $monthStart = now()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();

        $monthlyRequestCount = $business->chatRequests()
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $nextMonthStart)
            ->count();

        if ($monthlyRequestCount >= $business->monthly_request_limit) {
            return null;
        }

        return $business->chatRequests()->create([
            'from' => $from,
            'message' => $message,
            'status' => 'processing',
        ]);
    }
}
