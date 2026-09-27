<?php

namespace App\Services;

class ChatService
{
    public function __construct(
        private BusinessResolver $businessResolver,
        private EmbeddingService $embeddingService,
        private RetrievalService $retrievalService,
        private LLMService $llmService,
    ) {}

    /**
     * @param  array{to: string, from: string, message: string}  $data
     * @return array{message: string}|array{answer: string, source: string}
     */
    public function handle(array $data): array
    {
        $business = $this->businessResolver->resolve($data['to']);

        if ($business === null) {
            return ['message' => 'Business not found.'];
        }

        $queryEmbedding = $this->embeddingService->generate([$data['message']])[0];
        $matches = $this->retrievalService->retrieve($business->id, $queryEmbedding);

        if ($matches->isEmpty()) {
            return [
                'answer' => 'No encuentro información suficiente para responder esa pregunta.',
                'source' => 'no_context',
            ];
        }

        return [
            'answer' => $this->llmService->answer($data['message'], $matches),
            'source' => 'llm',
        ];
    }
}
