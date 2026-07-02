<?php

namespace App\Services\Market;

use App\Models\Product;

class AgentResponseBuilder
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array{content: string, meta: array<string, mixed>}
     */
    public static function make(string $content, array $meta = []): array
    {
        return [
            'content' => $content,
            'meta' => array_merge([
                'type' => 'text',
                'summary' => null,
                'products' => [],
                'follow_ups' => [],
                'thought_process' => [],
                'sources' => [],
            ], $meta),
        ];
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array{content: string, meta: array<string, mixed>}
     */
    public static function fromTrending(array $structured): array
    {
        return self::make($structured['markdown'], [
            'type' => 'trending',
            'summary' => $structured['summary'] ?? null,
            'products' => $structured['products'] ?? [],
            'trending_products' => $structured['trending_products'] ?? [],
            'follow_ups' => $structured['follow_ups'] ?? [],
            'thought_process' => $structured['thought_process'] ?? [],
            'sources' => $structured['sources'] ?? [],
            'query' => $structured['query'] ?? null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function defaultFollowUps(?string $category = null): array
    {
        $common = [
            'category fashion june 2026 500-2000 tk kon product trending',
            'top 5 electronics under 2000 tk trending',
            'trending product ki?',
        ];

        $byCategory = match ($category) {
            'fashion' => [
                "Show women's kurti under 1000 tk trending",
                'Filter panjabi 800-1500 tk trending',
                'Compare Daraz vs Shajgoj fashion trending',
            ],
            'electronics' => [
                'Show wireless earbuds under 1500 tk trending',
                'Filter power bank 600-2000 tk trending',
                'Compare Pickaboo vs Daraz electronics',
            ],
            'beauty' => [
                'Show lipstick under 800 tk trending',
                'Filter sunscreen 400-1500 tk trending',
            ],
            default => $common,
        };

        return array_slice($byCategory, 0, 3);
    }

    /**
     * @param  array<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public static function formatPlatformProducts(array $products): array
    {
        return collect($products)->map(function (Product $product) {
            $merchant = $product->merchant?->shop_name ?? 'ShipNest Seller';
            $name = $product->name;

            return [
                'id' => $product->id,
                'product_id' => $product->id,
                'slug' => $product->slug,
                'name' => $name,
                'price_label' => $product->formatted_price,
                'image' => self::resolvePlatformImage($product),
                'site' => $merchant,
                'merchant' => $merchant,
                'supplier' => $merchant,
                'url' => route('products.show', $product->slug),
                'inquiry_url' => null,
                'admin_url' => route('admin.products.edit', $product),
                'label' => $product->is_featured ? 'HOT' : 'MODERATE',
                'source' => 'platform',
                'match_label' => 'Matches all 1/1 requirements',
                'requirements_met' => 1,
                'requirements_total' => 1,
                'rating' => ($product->reviews_avg_rating ?? 0) > 0
                    ? round((float) $product->reviews_avg_rating, 1)
                    : null,
                'reviews' => (int) ($product->reviews_count ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public static function formatPlatformTrendingProducts(array $products): array
    {
        return collect(self::formatPlatformProducts($products))
            ->map(function (array $product) {
                $product['section'] = 'trending';
                $product['label'] = 'HOT';
                $product['match_label'] = 'Trending on ShipNest';

                return $product;
            })
            ->values()
            ->all();
    }

    private static function resolvePlatformImage(Product $product): string
    {
        $url = $product->primary_image_url;

        if ($url) {
            return $url;
        }

        $label = urlencode(mb_substr($product->name, 0, 24));

        return "https://placehold.co/400x300/F57C00/FFFFFF/png?text={$label}&font=roboto";
    }

    public static function buildPlatformSummary(string $term, int $shown, int $total): string
    {
        $q = ucfirst(trim($term));

        if ($shown === 0) {
            return "No **{$q}** products found on ShipNest catalog.";
        }

        $more = $total > $shown ? ' (showing top '.$shown.')' : '';

        return "I have found **{$total}** **{$q}** product(s) on **ShipNest**{$more}. "
            .'Browse real listings from your marketplace sellers below — with prices, images, and direct product links.';
    }

    /**
     * @return array<int, string>
     */
    public static function platformFollowUps(string $term): array
    {
        return [
            "{$term} under 2000 tk",
            "top 5 {$term} trending Bangladesh",
            "market demand for {$term} kemon",
        ];
    }

    /**
     * Trending research — name + price only from web search. No ShipNest catalog cards.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function formatTrendingNamesForUi(array $items): array
    {
        return collect($items)->map(function ($item) {
            $name = $item['product_name'] ?? $item['product'] ?? $item['name'] ?? '';
            $priceLabel = $item['price_label'] ?? $item['estimated_price'] ?? $item['reason'] ?? '';
            if (! $priceLabel && isset($item['price_bdt'])) {
                $priceLabel = '৳'.number_format((int) $item['price_bdt']);
            }
            $sourceUrls = $item['source_urls'] ?? [];
            if (! is_array($sourceUrls)) {
                $sourceUrls = [];
            }

            return [
                'name' => $name,
                'product_name' => $name,
                'category' => $item['category'] ?? null,
                'estimated_price' => $item['estimated_price'] ?? $priceLabel ?: null,
                'trend_score' => $item['trend_score'] ?? null,
                'reason' => $item['reason'] ?? null,
                'source_urls' => $sourceUrls,
                'price_label' => $priceLabel,
                'source' => 'trending_search',
                'external_url' => $sourceUrls[0] ?? $item['url'] ?? null,
                'site' => $item['site'] ?? null,
            ];
        })->filter(fn ($p) => ! empty($p['name']))->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function formatProductsForUi(array $items, ?string $category = null): array
    {
        return collect($items)->map(function ($item) use ($category) {
            $name = $item['product'] ?? $item['name'] ?? '';
            $priceLabel = $item['price_label'] ?? $item['reason'] ?? '';
            if (! $priceLabel && isset($item['price_bdt'])) {
                $priceLabel = '৳'.number_format((int) $item['price_bdt']);
            }

            $url = $item['url'] ?? '';

            return [
                'name' => $name,
                'price_label' => $priceLabel,
                'site' => $item['site'] ?? 'Market',
                'merchant' => $item['supplier'] ?? $item['site'] ?? 'Supplier',
                'supplier' => $item['supplier'] ?? $item['site'] ?? 'Supplier',
                'url' => $url,
                'inquiry_url' => self::alibabaInquiryUrl($url),
                'label' => $item['label'] ?? 'MODERATE',
                'section' => $item['section'] ?? 'general',
                'emoji' => self::productEmoji($name, $category),
                'source' => $item['source'] ?? 'market',
                'image' => $item['image'] ?? null,
                'match_label' => 'Matches all 1/1 requirements',
                'requirements_met' => 1,
                'requirements_total' => 1,
            ];
        })->filter(fn ($p) => ! empty($p['name']))->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public static function followUpsForQuery(string $question, ?string $category = null): array
    {
        $lower = strtolower(trim($question));

        if (str_contains($lower, 'watch') || str_contains($lower, 'ঘড়ি')) {
            return [
                'Show smartwatches with heart rate monitoring in Bangladesh',
                'Filter digital watches under 2000 tk on Daraz',
                'Compare watch prices Daraz vs Pickaboo',
            ];
        }

        if ($category) {
            return self::defaultFollowUps($category);
        }

        return [
            "top 5 {$question} trending Bangladesh",
            "{$question} under 2000 tk Daraz",
            "Compare {$question} prices Daraz vs Pickaboo",
        ];
    }

    public static function buildQaSummary(string $question, array $products, int $resultCount): string
    {
        $count = count($products);
        $q = ucfirst(trim($question));

        if ($count > 0) {
            return "I have found a diverse range of **{$q}** products for you ({$count} matches). "
                .'Results include listings from Daraz, Pickaboo, Shajgoj and other Bangladesh sites. '
                .'You can refine these results below.';
        }

        return "I searched the Bangladesh market for **{$q}** and found {$resultCount} related listings. "
            .'Try the refinement options below for more specific results.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $hits
     * @return array<int, array<string, mixed>>
     */
    public static function discoveryCardsFromHits(array $hits, string $query, int $topN = 5): array
    {
        $cards = [];
        $seen = [];
        $selector = app(SiteSelectorService::class);

        foreach ($hits as $hit) {
            $title = trim($hit['title'] ?? '');
            if (! $title) {
                continue;
            }

            $name = self::cleanHitTitle($title, $query);
            if (app(SearchHelpers::class)->isGenericProductName($name)) {
                $name = ucfirst($query).' — '.($hit['site'] ?? $selector->detectSiteFromUrl($hit['url'] ?? ''));
            }

            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $prices = [];
            $text = $title.' '.($hit['snippet'] ?? '');
            if (preg_match_all('/(?:৳|Tk\.?|BDT|tk)\s*([\d,]+)|([\d,]+)\s*(?:tk|taka|bdt)\b/i', $text, $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $prices[] = (int) str_replace(',', '', $match[1] ?: $match[2]);
                }
            }
            $price = $prices[0] ?? null;

            $url = $hit['url'] ?? '';
            $site = $hit['site'] ?? $selector->detectSiteFromUrl($url);

            $cards[] = [
                'name' => $name,
                'price_label' => $price ? '৳'.number_format($price) : 'See listing',
                'site' => $site,
                'merchant' => $site,
                'supplier' => $site,
                'url' => $url,
                'inquiry_url' => self::alibabaInquiryUrl($url),
                'label' => 'HOT',
                'emoji' => self::productEmoji($name, null),
            ];

            if (count($cards) >= $topN) {
                break;
            }
        }

        return $cards;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array{site: string, title: string, url: string}>
     */
    public static function extractSources(array $results): array
    {
        $selector = app(SiteSelectorService::class);

        return collect($results)
            ->filter(fn ($r) => ! empty($r['url']))
            ->take(6)
            ->map(fn ($r) => [
                'site' => $r['site'] ?? $selector->detectSiteFromUrl($r['url'] ?? ''),
                'title' => mb_substr($r['title'] ?? 'Listing', 0, 70),
                'url' => $r['url'],
            ])
            ->values()
            ->all();
    }

    public static function alibabaInquiryUrl(?string $url): ?string
    {
        if (! $url || ! str_contains(strtolower($url), 'alibaba.com')) {
            return null;
        }

        if (preg_match('/_(\d{8,})\.html/i', $url, $m)
            || preg_match('/\/product\/(\d{8,})/i', $url, $m)
            || preg_match('/chkProductIds=(\d{8,})/i', $url, $m)) {
            return 'https://message.alibaba.com/msgsend/contact.htm?action=contact_action&chkProductIds='.$m[1];
        }

        return null;
    }

    private static function cleanHitTitle(string $title, string $query): string
    {
        $name = preg_replace('/\bBuy\s+/i', '', $title);
        $name = preg_replace('/\s+Online\b.*$/i', '', $name ?? '');
        $name = preg_replace('/\s*[-|]\s*(Daraz|Pickaboo|Shajgoj|Othoba).*$/i', '', $name ?? '');
        $name = trim(strip_tags($name ?? ''));

        if (mb_strlen($name) > 55) {
            $name = mb_substr($name, 0, 52).'...';
        }

        return $name ?: ucfirst($query);
    }

    private static function productEmoji(string $name, ?string $category): string
    {
        $lower = strtolower($name);
        $map = [
            'kurti' => '👗', 'tops' => '👚', 'saree' => '🥻', 'leggings' => '🩱',
            'blouse' => '👚', 'palazzo' => '👖', 't-shirt' => '👕', 'shirt' => '👔',
            'panjabi' => '🕌', 'kurta' => '👔', 'hoodie' => '🧥', 'jacket' => '🧥',
            'jeans' => '👖', 'earbuds' => '🎧', 'watch' => '⌚', 'phone' => '📱',
            'lipstick' => '💄', 'perfume' => '🌸', 'fan' => '🌀',
        ];
        foreach ($map as $key => $emoji) {
            if (str_contains($lower, $key)) {
                return $emoji;
            }
        }

        return match ($category) {
            'fashion' => '👗',
            'electronics' => '📱',
            'beauty' => '💄',
            'home' => '🏠',
            'kids' => '🧸',
            'food' => '🍯',
            default => '🛍️',
        };
    }
}
