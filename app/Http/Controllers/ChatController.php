<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ChatResource;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    public function __invoke(StoreChatRequest $request): JsonResponse
    {
        $result = $this->chatService->handle(
            $request->validated()
        );

        return response()->json($result);
    }
}