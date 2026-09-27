<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppNumber extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_numbers';

    protected $fillable = [
        'business_id',
        'phone_number',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * A WhatsApp number identifies the tenant, but is not the tenant itself.
     * One business may have several numbers.
     */
    public static function resolveActiveBusinessId(string $phoneNumber): ?int
    {
        return static::query()
            ->where('phone_number', $phoneNumber)
            ->where('active', true)
            ->value('business_id');
    }
}