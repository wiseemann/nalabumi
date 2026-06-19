<?php

namespace App\Http\Controllers;

use App\Services\RagService;
use Illuminate\Http\Request;
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
        ]);

        $result = $ragService->answerQuestion($request->question);

        return response()->json($result);
    }
}
