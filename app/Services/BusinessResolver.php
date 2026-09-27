<?php

namespace App\Services;

use App\Models\Business;
use App\Models\WhatsAppNumber;

class BusinessResolver
{
    /**
     * Resolve the tenant from the WhatsApp number the message was sent to.
     * Returns null when the number is unknown or inactive — the caller
     * decides how to respond (controlled 404).
     */
    public function resolve(string $to): ?Business
    {
        $businessId = WhatsAppNumber::resolveActiveBusinessId($to);

        if ($businessId === null) {
            return null;
        }

        return Business::query()->find($businessId);
    }
}