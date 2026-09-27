<?php

namespace App\Services;

class ChatService
{
    public function handle(array $data): array
    {
        return [
                'answer' => 'Endpoint funcionando!',
                'source' => 'stub',
            ];
    }
}