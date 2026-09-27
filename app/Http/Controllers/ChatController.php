<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;

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

        $statusCode = isset($result['message']) ? 404 : 200;

        return response()->json($result, $statusCode);
    }
}
