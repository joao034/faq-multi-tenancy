<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Collection;

class FallbackService
{
    /**
     * @param  Collection<int, array{document: Document, similarity: float}>  $matches
     */
    public function answer(Collection $matches): ?string
    {
        foreach ($matches as $match) {
            $answer = trim($match['document']->answer);

            if ($answer !== '') {
                return $answer;
            }
        }

        return null;
    }
}
