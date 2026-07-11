<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Concerns\HandlesChatAgentSessions;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Product;
use App\Services\CartService;
use App\Services\Market\DemandChatAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ChatAgentController extends Controller
{
    use HandlesChatAgentSessions;

    public function bootstrap(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request, 'public_chat_session_key');
        $messages = $session->messages()->orderBy('id')->get();
        $formatted = [];

        foreach ($messages as $message) {
            if ($message->role === 'user') {
                $formatted[] = ['role' => 'user', 'content' => $message->content];

                continue;
            }

            $formatted[] = array_merge($this->formatMessagePayload($message), ['role' => 'assistant']);
        }

        return response()->json([
            'messages' => $formatted,
            'is_admin' => auth()->user()?->isAdmin() ?? false,
        ]);
    }

    public function send(Request $request, DemandChatAgent $agent): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'selected_product' => 'nullable|array',
            'selected_product.id' => 'nullable|integer',
            'selected_product.product_id' => 'nullable|integer',
            'selected_product.name' => 'nullable|string|max:500',
            'context_products' => 'nullable|array',
            'context_products.*.id' => 'nullable|integer',
            'context_products.*.product_id' => 'nullable|integer',
            'context_products.*.name' => 'nullable|string|max:500',
        ]);

        $message = trim((string) $request->input('message', ''));
        /** @var array<int, UploadedFile> $uploadedImages */
        $uploadedImages = array_values(array_filter(
            $request->file('images', []),
            fn ($file) => $file instanceof UploadedFile,
        ));

        if ($message === '' && $uploadedImages === []) {
            return response()->json(['message' => 'Message or image is required.'], 422);
        }

        try {
            $session = $this->resolveSession($request, 'public_chat_session_key');

            $userContent = $message !== ''
                ? $message
                : '📷 '.count($uploadedImages).' image(s) uploaded';

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'user',
                'content' => $userContent,
            ]);

            $selectedProduct = $request->input('selected_product');
            $contextProducts = $request->input('context_products', []);
            $response = $agent->handle(
                $session,
                $message,
                is_array($selectedProduct) ? $selectedProduct : null,
                is_array($contextProducts) ? $contextProducts : [],
                adminPanel: false,
                uploadedImages: $uploadedImages,
            );

            $assistant = ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'assistant',
                'content' => $response['content'],
                'meta' => $response['meta'],
            ]);

            return response()->json($this->formatMessagePayload($assistant));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'role' => 'assistant',
                'content' => '❌ কিছু একটা ভুল হয়েছে। আবার চেষ্টা করুন।',
                'type' => 'error',
                'meta' => ['type' => 'error'],
            ], 500);
        }
    }

    public function reset(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request, 'public_chat_session_key');
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
            'draft_product' => null,
        ]);
        $session->messages()->delete();
        $request->session()->forget('public_chat_session_key');

        return response()->json(['success' => true]);
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
}
