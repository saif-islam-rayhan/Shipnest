<?php

namespace App\Services\Market;

use App\Models\Product;
use App\Services\Market\Llm\LlmProviderManager;
use Throwable;

class ProductReviewGenerator
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly LlmProviderManager $providers,
    ) {}

    /**
     * @return array{rating: int, title: string, body: string}
     */
    public function generate(Product $product): array
    {
        $product->loadMissing(['category', 'brand']);

        if ($this->providers->isReady() && $this->providers->isLiveEnabled()) {
            try {
                return $this->generateWithLlm($product);
            } catch (Throwable) {
                // Fall through to template so the form still works offline / on API errors.
            }
        }

        return $this->fallback($product);
    }

    /**
     * @return array{rating: int, title: string, body: string}
     */
    private function generateWithLlm(Product $product): array
    {
        $facts = array_filter([
            'Product name: '.$product->name,
            $product->category?->name ? 'Category: '.$product->category->name : null,
            $product->brand?->name ? 'Brand: '.$product->brand->name : null,
            filled($product->short_description) ? 'Short description: '.$product->short_description : null,
            filled($product->price) ? 'Price: '.$product->formatted_price : null,
        ]);

        $system = <<<'PROMPT'
You write short, genuine-sounding POSITIVE customer reviews for ShipNest (Bangladesh ecommerce).
Always be positive and helpful (4–5 star experience). Sound like a real buyer, not an ad.
Write in natural Bangla-English mix (Banglish) when it fits, or clear English.
Return ONLY valid JSON with keys: rating (integer 4 or 5), title (max 80 chars), body (2–4 short sentences, max 500 chars).
No markdown fences, no extra keys, no apologies.
PROMPT;

        $user = "Write a positive review for this product:\n".implode("\n", $facts);

        $text = trim($this->llm->chat('', $system, $user, false, 0.7));
        $text = preg_replace('/^```(?:json|JSON)?\s*|\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            return $this->fallback($product);
        }

        $title = trim((string) ($decoded['title'] ?? ''));
        $body = trim((string) ($decoded['body'] ?? ''));
        $rating = (int) ($decoded['rating'] ?? 5);

        if ($title === '' || $body === '') {
            return $this->fallback($product);
        }

        return [
            'rating' => max(4, min(5, $rating)),
            'title' => mb_substr($title, 0, 255),
            'body' => mb_substr($body, 0, 2000),
        ];
    }

    /**
     * @return array{rating: int, title: string, body: string}
     */
    private function fallback(Product $product): array
    {
        $name = trim((string) $product->name) ?: 'this product';
        $category = $product->category?->name;

        $titles = [
            "Really happy with {$name}",
            'Great quality — recommended',
            'Worth buying from ShipNest',
            'Solid product, fast delivery',
        ];

        $bodies = [
            "{$name} এর quality ভালো লেগেছে। Packaging neat ছিল এবং deliveryও timely। Definitely recommend করব।",
            "Bought {$name} and I'm satisfied. Looks good, works as expected, and value for money is solid.",
            ($category ? "Nice pick in {$category}. " : '')
                ."{$name} use করে খুশি। Build quality ভালো এবং overall experience positive।",
        ];

        return [
            'rating' => 5,
            'title' => $titles[array_rand($titles)],
            'body' => $bodies[array_rand($bodies)],
        ];
    }
}
