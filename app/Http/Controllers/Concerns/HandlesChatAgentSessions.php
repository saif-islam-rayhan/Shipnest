<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait HandlesChatAgentSessions
{
    /**
     * @return array<string, mixed>
     */
    protected function formatMessagePayload(ChatMessage $message): array
    {
        $meta = $message->meta ?? [];

        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'content_html' => Str::markdown($message->content),
            'meta' => $meta,
            'summary' => $meta['summary'] ?? null,
            'products' => $meta['products'] ?? [],
            'products_all' => $meta['products_all'] ?? [],
            'products_preview_count' => $meta['products_preview_count'] ?? 4,
            'trending_products' => $meta['trending_products'] ?? [],
            'trending_products_all' => $meta['trending_products_all'] ?? [],
            'trending_total_count' => $meta['trending_total_count'] ?? null,
            'trending_preview_count' => $meta['trending_preview_count'] ?? 4,
            'follow_ups' => $meta['follow_ups'] ?? [],
            'thought_process' => $meta['thought_process'] ?? [],
            'sources' => $meta['sources'] ?? [],
            'type' => $meta['type'] ?? 'text',
            'greeting' => (bool) ($meta['greeting'] ?? false),
            'show_content' => (bool) ($meta['show_content'] ?? false),
            'catalog_mode' => $meta['catalog_mode'] ?? null,
            'total_count' => $meta['total_count'] ?? null,
            'query' => $meta['query'] ?? null,
            'cart_url' => $meta['cart_url'] ?? null,
            'checkout_url' => $meta['checkout_url'] ?? null,
            'draft_product' => $meta['draft_product'] ?? null,
            'product' => $meta['product'] ?? null,
        ];
    }

    protected function resolveSession(Request $request, string $sessionKeyName = 'admin_chat_session_key'): ChatSession
    {
        $userId = auth()->id();
        $key = $request->session()->get($sessionKeyName);

        if ($key) {
            $query = ChatSession::query()->where('session_key', $key);

            if ($userId) {
                $session = (clone $query)->where('user_id', $userId)->first();
            } else {
                $session = (clone $query)->whereNull('user_id')->first();
            }

            if ($session) {
                return $session;
            }
        }

        $key = Str::random(40);
        $session = ChatSession::create([
            'session_key' => $key,
            'user_id' => $userId,
            'step' => 'idle',
            'top_n' => 5,
        ]);
        $request->session()->put($sessionKeyName, $key);

        return $session;
    }
}
