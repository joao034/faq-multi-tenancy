<?php

namespace App\Services;

use Laravel\Ai\Embeddings;

class EmbeddingService
{
    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function generate(array $texts): array
    {
        return Embeddings::for($texts)
            ->generate()
            ->embeddings;
    }
}
