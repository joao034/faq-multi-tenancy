<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'from',
        'message',
        'model',
        'tokens',
        'status',
    ];

    protected $casts = [
        'tokens' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
