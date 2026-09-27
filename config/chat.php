<?php
 
return [
    // Retrieval
    'retrieval_top_k' => (int) env('RETRIEVAL_TOP_K', 3),
    'retrieval_similarity_threshold' => (float) env('RETRIEVAL_SIMILARITY_THRESHOLD', 0.75),
 
    // Usage / limits
    'default_monthly_request_limit' => (int) env('DEFAULT_MONTHLY_REQUEST_LIMIT', 500),
];
 
