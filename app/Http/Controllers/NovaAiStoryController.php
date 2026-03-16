<?php

namespace App\Http\Controllers;

use App\Services\AIStoryQaService;
use Illuminate\Http\Request;

class NovaAiStoryController extends Controller
{
    public function show(Request $request)
    {
        return view('nova-ai.story', [
            'answer' => session('nova_ai_answer'),
            'error' => session('nova_ai_error'),
            'question' => session('nova_ai_question'),
        ]);
    }

    public function ask(Request $request, AIStoryQaService $service)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3'],
        ]);

        try {
            $answer = $service->ask((string) $data['question']);

            return redirect()
                ->route('nova-ai.story')
                ->with([
                    'nova_ai_answer' => $answer,
                    'nova_ai_question' => (string) $data['question'],
                ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('nova-ai.story')
                ->with([
                    'nova_ai_error' => $e->getMessage(),
                    'nova_ai_question' => (string) $data['question'],
                ]);
        }
    }
}

