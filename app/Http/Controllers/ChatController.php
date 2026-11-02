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
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:4000',
            'stream' => 'nullable|boolean',
        ]);

        // Rate limiting: 15 requests per minute per IP
        $key = 'chat:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 15)) {
            $msg = 'Anda terlalu sering bertanya. Silakan tunggu sebentar sebelum mencoba lagi.';
            if ($request->boolean('stream', true)) {
                return response()->stream(function() use ($msg) {
                    echo "data: " . json_encode(['type' => 'error', 'message' => $msg]) . "\n\n";
                    echo "data: " . json_encode(['type' => 'done']) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }, 429, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache, no-transform',
                    'Connection' => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                ]);
            }
            return response()->json([
                'answer' => $msg,
                'thought' => null,
                'sources' => [],
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $question = $request->input('question');
        $location = $request->input('location');
        $history = $request->input('history', []);
        $wantsStream = $request->boolean('stream', true);

        if ($wantsStream) {
            return response()->stream(function() use ($ragService, $question, $location, $history) {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }

                foreach ($ragService->streamAnswer($question, $location, $history) as $chunk) {
                    echo "data: " . json_encode($chunk) . "\n\n";
                    flush();
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $result = $ragService->answerQuestion(
            $question,
            $location,
            $history
        );

        return response()->json($result);
    }
}
