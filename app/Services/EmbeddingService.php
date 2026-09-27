<?php

namespace App\Services;

use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class EmbeddingService
{
    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function generate(array $texts): array
    {
        return Embeddings::for($texts)
            ->dimensions((int) config('chat.embedding_dimensions'))
            ->generate(
                provider: Lab::Gemini,
                model: (string) config('chat.embedding_model'),
            )
            ->embeddings;
    }
}
