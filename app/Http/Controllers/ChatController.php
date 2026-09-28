<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    public function __invoke(StoreChatRequest $request): JsonResponse
    {
        try {
            $result = $this->chatService->handle($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to process request.'], 500);
        }

        $statusCode = $result['http_status'] ?? (isset($result['message']) ? 404 : 200);
        unset($result['http_status']);

        return response()->json($result, $statusCode);
    }
}
