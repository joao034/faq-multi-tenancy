<?php
return [
    'default' => env('AI_PROVIDER', 'gemini'),
    'default_for_embeddings' => 'gemini',

    'providers' => [
        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),

            'models' => [
                'embeddings' => [
                    'default' => env('EMBEDDING_MODEL', 'gemini-embedding-001'),
                    'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 768),
                ],
            ],
        ],
    ],
];
