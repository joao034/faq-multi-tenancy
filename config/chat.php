<?php
 
return [
    // LLM Service
    'llm_model' => env('LLM_MODEL', 'gemini-3.1-flash-lite'),
    'llm_api_key' => env('LLM_API_KEY'),
 
    // Embeddings
    'embedding_model' => env('EMBEDDING_MODEL', 'gemini-embedding-001'),
    'embedding_dimensions' => (int) env('EMBEDDING_DIMENSIONS', 768),
 
    // Retrieval
    'retrieval_top_k' => (int) env('RETRIEVAL_TOP_K', 3),
    'retrieval_similarity_threshold' => (float) env('RETRIEVAL_SIMILARITY_THRESHOLD', 0.75),
 
    // Usage / limits
    'default_monthly_request_limit' => (int) env('DEFAULT_MONTHLY_REQUEST_LIMIT', 500),
];
 
