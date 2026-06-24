<?php

namespace App\Http\Controllers;

use App\Services\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function index()
    {
        return Inertia::render('Chat');
    }

    public function ask(Request $request, RagService $ragService)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'location' => 'nullable|string|max:255',
        ]);

        // Rate limiting: 10 requests per minute per IP
        $key = 'chat:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'answer' => 'Anda terlalu sering bertanya. Silakan tunggu sebentar sebelum mencoba lagi.',
                'thought' => null,
                'sources' => [],
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $result = $ragService->answerQuestion(
            $request->question,
            $request->location
        );

        return response()->json($result);
    }
}
