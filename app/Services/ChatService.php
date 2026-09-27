<?php

namespace App\Services;



class ChatService
{

    public function __construct(private BusinessResolver $businessResolver){}

    public function handle(array $data): array
    {
        $business = $this->businessResolver->resolve($data['to']);
 
        if ($business === null) {
            return [
                ['message' => 'Business not found.'],
            ];
        }
 
        // Placeholder until retrieval + LLM are in place.
        return [
            [
                'answer' => "Tenant: {$business->name}.",
                'source' => 'stub',
            ],
        ];
    }
}