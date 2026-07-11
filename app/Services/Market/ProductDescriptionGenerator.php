<?php

namespace App\Services\Market;

use App\Services\Market\Llm\LlmProviderManager;
use Throwable;

class ProductDescriptionGenerator
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly LlmProviderManager $providers,
    ) {}

    /**
     * @param  array{
     *     name?: string,
     *     category?: string,
     *     brand?: string,
     *     sku?: string,
     *     short_description?: string,
     *     price?: string|float|null,
     *     attributes?: array<int, array{name?: string, value?: string}>
     * }  $data
     */
    public function generate(array $data): string
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('Product name is required to generate a description.');
        }

        if ($this->providers->isReady() && $this->providers->isLiveEnabled()) {
            try {
                return $this->generateWithLlm($data);
            } catch (Throwable) {
                // Fall through to template so the form still works offline / on API errors.
            }
        }

        return $this->fallback($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function generateWithLlm(array $data): string
    {
        $attrs = collect($data['attributes'] ?? [])
            ->filter(fn ($a) => filled($a['name'] ?? null) && filled($a['value'] ?? null))
            ->map(fn ($a) => trim(($a['name'] ?? '').': '.($a['value'] ?? '')))
            ->implode('; ');

        $facts = array_filter([
            'Product name: '.($data['name'] ?? ''),
            filled($data['category'] ?? null) ? 'Category: '.$data['category'] : null,
            filled($data['brand'] ?? null) ? 'Brand: '.$data['brand'] : null,
            filled($data['sku'] ?? null) ? 'SKU: '.$data['sku'] : null,
            filled($data['price'] ?? null) ? 'Price: '.$data['price'] : null,
            filled($data['short_description'] ?? null) ? 'Short description: '.$data['short_description'] : null,
            $attrs !== '' ? 'Attributes: '.$attrs : null,
        ]);

        $system = <<<'PROMPT'
You write ecommerce product descriptions for ShipNest (Bangladesh marketplace).
Write in clear Bangla-English mix or natural Bangla when the product name is Bangla; otherwise use clear English.
Return ONLY the description body as plain text (no title, no markdown fences).
Use 2–4 short paragraphs or bullet-like lines. Highlight benefits, use cases, and key details from the facts.
Do not invent warranties, certifications, or specs that are not in the facts.
PROMPT;

        $user = "Create a product description from these facts:\n".implode("\n", $facts);

        $text = trim($this->llm->chat('', $system, $user, false, 0.5));
        $text = preg_replace('/^```(?:\w+)?\s*|\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return $this->fallback($data);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fallback(array $data): string
    {
        $name = trim((string) ($data['name'] ?? 'This product'));
        $category = trim((string) ($data['category'] ?? ''));
        $brand = trim((string) ($data['brand'] ?? ''));
        $short = trim((string) ($data['short_description'] ?? ''));
        $price = $data['price'] ?? null;

        $lines = [];
        $intro = $name;
        if ($brand !== '') {
            $intro .= " by {$brand}";
        }
        if ($category !== '') {
            $intro .= " — a great pick in {$category}";
        }
        $lines[] = $intro.'.';

        if ($short !== '') {
            $lines[] = $short;
        }

        $attrs = collect($data['attributes'] ?? [])
            ->filter(fn ($a) => filled($a['name'] ?? null) && filled($a['value'] ?? null))
            ->map(fn ($a) => '• '.trim($a['name']).': '.trim($a['value']))
            ->values()
            ->all();

        if ($attrs !== []) {
            $lines[] = "Key details:\n".implode("\n", $attrs);
        }

        if (filled($price)) {
            $symbol = config('shipnest.currency_symbol', '৳');
            $lines[] = "Available now at {$symbol}".number_format((float) $price).'.';
        }

        $lines[] = 'Order today from ShipNest for reliable delivery across Bangladesh.';

        return implode("\n\n", $lines);
    }
}
