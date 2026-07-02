<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Product;
use App\Services\CartService;
use App\Services\Market\AgentResponseBuilder;
use App\Services\Market\DemandChatAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChatAgentController extends Controller
{
    public function index(Request $request): View
    {
        $session = $this->resolveSession($request);
        $messages = $session->messages()->orderBy('id')->get();

        return view('admin.agent.index', [
            'session' => $session,
            'messages' => $messages,
            'defaultFollowUps' => AgentResponseBuilder::defaultFollowUps(),
        ]);
    }

    public function send(Request $request, DemandChatAgent $agent): JsonResponse|RedirectResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'selected_product' => 'nullable|array',
            'selected_product.id' => 'nullable|integer',
            'selected_product.product_id' => 'nullable|integer',
            'selected_product.name' => 'nullable|string|max:500',
            'context_products' => 'nullable|array',
            'context_products.*.id' => 'nullable|integer',
            'context_products.*.product_id' => 'nullable|integer',
            'context_products.*.name' => 'nullable|string|max:500',
        ]);
        $session = $this->resolveSession($request);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        $selectedProduct = $request->input('selected_product');
        $contextProducts = $request->input('context_products', []);
        $response = $agent->handle(
            $session,
            $request->message,
            is_array($selectedProduct) ? $selectedProduct : null,
            is_array($contextProducts) ? $contextProducts : [],
        );

        $assistant = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $response['content'],
            'meta' => $response['meta'],
        ]);

        $payload = $this->formatMessagePayload($assistant);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return redirect()->route('admin.agent.index');
    }

    public function reset(Request $request): JsonResponse|RedirectResponse
    {
        $session = $this->resolveSession($request);
        $session->update([
            'step' => 'idle',
            'question' => null,
            'category' => null,
            'budget_min' => null,
            'budget_max' => null,
            'month_from' => null,
            'month_to' => null,
            'year_from' => null,
            'year_to' => null,
            'top_n' => 5,
            'pending_cart_product_id' => null,
        ]);
        $session->messages()->delete();
        $request->session()->forget('admin_chat_session_key');

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('admin.agent.index')->with('success', 'Chat reset হয়েছে।');
    }

    public function addToCart(Request $request, CartService $cartService): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1|max:99',
        ]);

        $product = Product::query()->active()->inStock()->findOrFail($request->integer('product_id'));

        try {
            $cartService->add($product->id, null, $request->integer('quantity', 1));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "{$product->name} cart-এ add হয়েছে!",
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price_label' => $product->formatted_price,
            ],
            'cart_url' => route('cart.index'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessagePayload(ChatMessage $message): array
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
            'total_count' => $meta['total_count'] ?? null,
            'query' => $meta['query'] ?? null,
            'cart_url' => $meta['cart_url'] ?? null,
            'checkout_url' => $meta['checkout_url'] ?? null,
        ];
    }

    private function resolveSession(Request $request): ChatSession
    {
        $key = $request->session()->get('admin_chat_session_key');
        if ($key) {
            $session = ChatSession::where('session_key', $key)
                ->where('user_id', auth()->id())
                ->first();
            if ($session) {
                return $session;
            }
        }

        $key = Str::random(40);
        $session = ChatSession::create([
            'session_key' => $key,
            'user_id' => auth()->id(),
            'step' => 'idle',
            'top_n' => 5,
        ]);
        $request->session()->put('admin_chat_session_key', $key);

        return $session;
    }
}
