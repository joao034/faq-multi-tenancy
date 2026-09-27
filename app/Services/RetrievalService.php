<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Collection;

class RetrievalService
{
    /**
     * @param  array<int, float>  $queryEmbedding
     * @return Collection<int, array{document: Document, similarity: float}>
     */
    public function retrieve(int $businessId, array $queryEmbedding): Collection
    {
        $minimumSimilarity = (float) config('chat.retrieval_similarity_threshold');
        $topK = (int) config('chat.retrieval_top_k');

        return Document::query()
            ->forBusiness($businessId)
            ->whereNotNull('embedding')
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): array => [
                'document' => $document,
                'similarity' => $this->cosineSimilarity($queryEmbedding, $document->embedding ?? []),
            ])
            ->sortByDesc('similarity')
            ->take($topK)
            ->filter(fn (array $match): bool => $match['similarity'] >= $minimumSimilarity)
            ->values();
    }

    /**
     * @param  array<int, float>  $queryEmbedding
     * @param  array<int, float>  $documentEmbedding
     */
    private function cosineSimilarity(array $queryEmbedding, array $documentEmbedding): float
    {
        if ($queryEmbedding === [] || count($queryEmbedding) !== count($documentEmbedding)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $queryMagnitude = 0.0;
        $documentMagnitude = 0.0;

        foreach ($queryEmbedding as $index => $queryValue) {
            $documentValue = $documentEmbedding[$index];

            $dotProduct += $queryValue * $documentValue;
            $queryMagnitude += $queryValue ** 2;
            $documentMagnitude += $documentValue ** 2;
        }

        if ($queryMagnitude === 0.0 || $documentMagnitude === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($queryMagnitude) * sqrt($documentMagnitude));
    }
}
