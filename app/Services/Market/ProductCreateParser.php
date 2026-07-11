<?php

namespace App\Services\Market;

class ProductCreateParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $message): array
    {
        $data = [];
        $text = trim($message);

        if ($text === '') {
            return $data;
        }

        if ($this->looksLikeJson($text)) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return $this->normalizePayload($decoded);
            }
        }

        $this->extractKeyValuePairs($text, $data);
        $this->extractVariants($text, $data);
        $this->extractAttributes($text, $data);
        $this->extractImageUrls($text, $data);

        return $this->normalizePayload($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizePayload(array $data): array
    {
        $normalized = [];

        foreach ([
            'merchant', 'merchant_id', 'name', 'category', 'category_id',
            'brand', 'brand_id', 'sku', 'short_description', 'description',
            'meta_title', 'meta_description', 'tags', 'approval_status',
            'image_urls', 'action',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $normalized[$key] = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
            }
        }

        if (isset($data['price'])) {
            $normalized['price'] = $this->toFloat($data['price']);
        }
        if (isset($data['compare_price'])) {
            $normalized['compare_price'] = $this->toFloat($data['compare_price']);
        }
        if (isset($data['stock'])) {
            $normalized['stock'] = $this->toInt($data['stock']);
        }
        if (isset($data['weight'])) {
            $normalized['weight'] = $this->toFloat($data['weight']);
        }
        if (isset($data['is_featured'])) {
            $normalized['is_featured'] = $this->toBool($data['is_featured']);
        }
        if (isset($data['publish'])) {
            $normalized['publish'] = $this->toBool($data['publish']);
        }

        if (! empty($data['variants']) && is_array($data['variants'])) {
            $normalized['variants'] = array_values(array_map(
                fn (array $v) => $this->normalizeVariant($v),
                array_filter($data['variants'], 'is_array'),
            ));
        }

        if (! empty($data['attributes']) && is_array($data['attributes'])) {
            $normalized['attributes'] = array_values(array_filter(array_map(
                fn ($a) => is_array($a) ? [
                    'name' => trim((string) ($a['name'] ?? '')),
                    'value' => trim((string) ($a['value'] ?? '')),
                ] : null,
                $data['attributes'],
            )));
        }

        if (isset($normalized['price']) && empty($normalized['variants'])) {
            $normalized['variants'] = [[
                'name' => 'Default',
                'sku' => $normalized['sku'] ?? '',
                'price' => $normalized['price'],
                'compare_price' => $normalized['compare_price'] ?? null,
                'stock' => $normalized['stock'] ?? 0,
                'weight' => $normalized['weight'] ?? null,
            ]];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractKeyValuePairs(string $text, array &$data): void
    {
        $map = [
            'merchant' => '/\b(?:merchant|shop|store|দোকান)\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'name' => '/\b(?:name|product(?:\s+name)?|পণ্য(?:\s+নাম)?)\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'category' => '/\b(?:category|ক্যাটাগরি)\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'brand' => '/\b(?:brand|ব্র্যান্ড)\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'sku' => '/\bsku\s*[:=]\s*([A-Za-z0-9\-_]+)/iu',
            'price' => '/\b(?:price|দাম|মূল্য)\s*[:=]\s*([\d,]+(?:\.\d+)?)/iu',
            'compare_price' => '/\b(?:compare(?:\s*price)?|was\s*price|original)\s*[:=]\s*([\d,]+(?:\.\d+)?)/iu',
            'stock' => '/\b(?:stock|স্টক)\s*[:=]\s*(\d+)/iu',
            'weight' => '/\bweight\s*[:=]\s*([\d.]+)/iu',
            'short_description' => '/\bshort(?:\s*description)?\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'description' => '/\b(?:description|বিবরণ)\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'meta_title' => '/\bmeta\s*title\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'meta_description' => '/\bmeta\s*description\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'tags' => '/\btags\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu',
            'approval_status' => '/\bapproval\s*[:=]\s*(approved|pending|rejected)/iu',
            'action' => '/\b(?:action|status)\s*[:=]\s*(draft|publish|active)/iu',
        ];

        foreach ($map as $key => $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $data[$key] = trim($m[1]);
            }
        }

        if (preg_match('/\b(?:featured|is_featured)\s*[:=]\s*(yes|no|true|false|1|0|হ্যাঁ|না)/iu', $text, $m)) {
            $data['is_featured'] = $m[1];
        }

        if (preg_match('/\b(?:publish|published)\s*[:=]\s*(yes|no|true|false|1|0|হ্যাঁ|না)/iu', $text, $m)) {
            $data['publish'] = $m[1];
        }

        if (preg_match('/\bmerchant\s*#?(\d+)\b/i', $text, $m)) {
            $data['merchant_id'] = (int) $m[1];
        }
        if (preg_match('/\bcategory\s*#?(\d+)\b/i', $text, $m)) {
            $data['category_id'] = (int) $m[1];
        }
        if (preg_match('/\bbrand\s*#?(\d+)\b/i', $text, $m)) {
            $data['brand_id'] = (int) $m[1];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractVariants(string $text, array &$data): void
    {
        if (! empty($data['variants'])) {
            return;
        }

        if (! preg_match_all('/\bvariant\s*(\d+)?\s*[:=]\s*([^;]+)(?:;|$)/iu', $text, $matches, PREG_SET_ORDER)) {
            return;
        }

        $variants = [];
        foreach ($matches as $match) {
            $chunk = trim($match[2]);
            $variant = ['name' => 'Default', 'sku' => '', 'price' => null, 'compare_price' => null, 'stock' => 0, 'weight' => null];

            if (preg_match('/\bname\s*[:=]\s*([^,]+)/iu', $chunk, $m)) {
                $variant['name'] = trim($m[1]);
            } elseif (! preg_match('/\bprice\b/i', $chunk) && str_contains($chunk, ',')) {
                $variant['name'] = trim(explode(',', $chunk)[0]);
            }

            if (preg_match('/\bprice\s*[:=]\s*([\d,]+(?:\.\d+)?)/iu', $chunk, $m)) {
                $variant['price'] = $this->toFloat($m[1]);
            }
            if (preg_match('/\bcompare\s*[:=]\s*([\d,]+(?:\.\d+)?)/iu', $chunk, $m)) {
                $variant['compare_price'] = $this->toFloat($m[1]);
            }
            if (preg_match('/\bstock\s*[:=]\s*(\d+)/iu', $chunk, $m)) {
                $variant['stock'] = (int) $m[1];
            }
            if (preg_match('/\bsku\s*[:=]\s*([A-Za-z0-9\-_]+)/iu', $chunk, $m)) {
                $variant['sku'] = trim($m[1]);
            }
            if (preg_match('/\bweight\s*[:=]\s*([\d.]+)/iu', $chunk, $m)) {
                $variant['weight'] = $this->toFloat($m[1]);
            }

            if ($variant['price'] !== null) {
                $variants[] = $this->normalizeVariant($variant);
            }
        }

        if ($variants !== []) {
            $data['variants'] = $variants;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractAttributes(string $text, array &$data): void
    {
        if (! empty($data['attributes'])) {
            return;
        }

        $attributes = [];

        if (preg_match('/\battributes?\s*[:=]\s*(.+?)(?=\s*,\s*(?:image|description|tags|meta)\s*[:=]|$)/iu', $text, $m)) {
            foreach (preg_split('/\s*[,;]\s*/', trim($m[1])) as $pair) {
                $attr = $this->parseAttributePair($pair);
                if ($attr) {
                    $attributes[] = $attr;
                }
            }
        }

        if (preg_match_all('/\battribute\s+([^:=]+)\s*[:=]\s*([^,;]+)/iu', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[] = [
                    'name' => trim($match[1]),
                    'value' => trim($match[2]),
                ];
            }
        }

        if ($attributes !== []) {
            $data['attributes'] = $attributes;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractImageUrls(string $text, array &$data): void
    {
        if (! empty($data['image_urls'])) {
            return;
        }

        $urls = [];

        if (preg_match('/\b(?:image(?:\s*urls?)?|images?)\s*[:=]\s*(.+?)(?=\s*,\s*\w+\s*[:=]|$)/iu', $text, $m)) {
            foreach (preg_split('/\s*[,;\n]\s*/', trim($m[1])) as $part) {
                if (filter_var($part, FILTER_VALIDATE_URL)) {
                    $urls[] = $part;
                }
            }
        }

        if (preg_match_all('#https?://[^\s,;]+#i', $text, $matches)) {
            foreach ($matches[0] as $url) {
                $urls[] = rtrim($url, '.,)');
            }
        }

        if ($urls !== []) {
            $data['image_urls'] = array_values(array_unique($urls));
        }
    }

    /**
     * @return array{name: string, value: string}|null
     */
    private function parseAttributePair(string $pair): ?array
    {
        if (! str_contains($pair, ':')) {
            return null;
        }

        [$name, $value] = array_map('trim', explode(':', $pair, 2));
        if ($name === '' || $value === '') {
            return null;
        }

        return ['name' => $name, 'value' => $value];
    }

    /**
     * @param  array<string, mixed>  $variant
     * @return array<string, mixed>
     */
    private function normalizeVariant(array $variant): array
    {
        return [
            'name' => trim((string) ($variant['name'] ?? 'Default')) ?: 'Default',
            'sku' => trim((string) ($variant['sku'] ?? '')),
            'price' => $this->toFloat($variant['price'] ?? 0) ?? 0,
            'compare_price' => isset($variant['compare_price']) ? $this->toFloat($variant['compare_price']) : null,
            'stock' => $this->toInt($variant['stock'] ?? 0) ?? 0,
            'weight' => isset($variant['weight']) ? $this->toFloat($variant['weight']) : null,
        ];
    }

    private function looksLikeJson(string $text): bool
    {
        $trimmed = trim($text);

        return (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))
            || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'));
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && preg_match('/([\d,]+(?:\.\d+)?)/', $value, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        $float = $this->toFloat($value);

        return $float !== null ? (int) $float : null;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $lower = strtolower(trim((string) $value));

        return in_array($lower, ['1', 'true', 'yes', 'y', 'publish', 'active', 'হ্যাঁ', 'ha', 'haa'], true);
    }
}
