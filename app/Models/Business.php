<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'monthly_request_limit',
    ];

    protected $casts = [
        'monthly_request_limit' => 'integer',
    ];

    public function whatsAppNumbers(): HasMany
    {
        return $this->hasMany(WhatsAppNumber::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function chatRequests(): HasMany
    {
        return $this->hasMany(ChatRequest::class);
    }
}