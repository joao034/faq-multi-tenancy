<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'title',
        'question',
        'answer',
        'embedding',
    ];

    protected $casts = [
        // 768-dim float vector stored as a JSON array.
        'embedding' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Hard tenant boundary: retrieval must never run before this filter.
     * Every query built for semantic search starts from this scope.
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Text used to build the embedding, mirroring the spec's format:
     * "Título: ...\nPregunta: ...\nRespuesta: ..."
     */
    public function embeddingSourceText(): string
    {
        return "Título: {$this->title}\nPregunta: {$this->question}\nRespuesta: {$this->answer}";
    }
}