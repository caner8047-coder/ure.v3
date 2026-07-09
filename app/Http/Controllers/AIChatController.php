<?php

namespace App\Http\Controllers;

use App\Services\AIBrainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AIChatController extends Controller
{
    /**
     * AI Chat sayfasini goster.
     */
    public function index()
    {
        $messages = Session::get('ai_chat_messages', []);
        return view('admin.ai-chat', compact('messages'));
    }

    /**
     * Mesaj gonder ve cevap al.
     */
    public function send(Request $request)
    {
        $soru = trim($request->input('message', ''));
        if ($soru === '') {
            return response()->json(['success' => false, 'message' => 'Bos mesaj gonderilemez.']);
        }

        // Mesaji session'a ekle
        $messages = Session::get('ai_chat_messages', []);
        $messages[] = [
            'role' => 'user',
            'text' => $soru,
            'time' => now()->format('H:i'),
        ];

        // AI cevabini al (session bazli memory icin chatId)
        $ai = app(AIBrainService::class);
        $chatId = 'web_' . session()->getId();
        $cevap = $ai->soruCevapla($soru, $chatId);

        $messages[] = [
            'role' => 'assistant',
            'text' => $cevap,
            'time' => now()->format('H:i'),
        ];

        // Son 50 mesaji sakla
        if (count($messages) > 50) {
            $messages = array_slice($messages, -50);
        }

        Session::put('ai_chat_messages', $messages);

        return response()->json([
            'success' => true,
            'reply' => $cevap,
            'time' => now()->format('H:i'),
        ]);
    }

    /**
     * Chati temizle.
     */
    public function clear()
    {
        Session::forget('ai_chat_messages');
        return response()->json(['success' => true]);
    }
}
