<?php

namespace App\Services\Market;

class SearchHelpers
{
    private const GENERIC = [
        'smartphones', 'electronics', 'fashion', 'gadgets', 'products', 'items',
        'clothing', 'appliances', 'cosmetics', 'beauty products',
        'best sellers', 'best seller', 'online shopping', 'most selling products',
        'most selling', 'online store', 'best selling', 'top selling', 'hot deals',
        'e-commerce', 'ecommerce', 'shopping sites', 'online shopping sites',
    ];

    public function compactResultsJson(array $results, int $maxItems = 6): string
    {
        $compact = collect($results)->take($maxItems)->map(function ($r) {
            $snippet = $r['snippet'] ?? $r['body'] ?? '';
            $limit = ($r['source'] ?? '') === 'page_fetch' ? 2000 : 400;

            return [
                'title' => mb_substr($r['title'] ?? '', 0, 100),
                'snippet' => mb_substr($snippet, 0, $limit),
                'url' => mb_substr($r['url'] ?? '', 0, 200),
                'prices' => $this->extractPrices(($r['title'] ?? '').' '.$snippet),
            ];
        })->values()->all();

        return json_encode($compact, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Deduplicate search hits by URL and normalized title.
     *
     * @return array<int, array<string, mixed>>
     */
    public function deduplicateSearchResults(array $results, int $max = 20): array
    {
        $deduped = [];
        $seenUrls = [];
        $seenTitles = [];

        foreach ($results as $r) {
            $url = strtolower(trim($r['url'] ?? ''));
            $title = strtolower(trim(preg_replace('/\s+/', ' ', $r['title'] ?? '') ?? ''));

            if ($url !== '' && isset($seenUrls[$url])) {
                continue;
            }
            if ($title !== '' && isset($seenTitles[$title])) {
                continue;
            }

            if ($url !== '') {
                $seenUrls[$url] = true;
            }
            if ($title !== '') {
                $seenTitles[$title] = true;
            }

            $deduped[] = $r;

            if (count($deduped) >= $max) {
                break;
            }
        }

        return $deduped;
    }

    /**
     * Build structured corpus from search results for LLM analysis.
     *
     * @return array{results: array<int, array<string, mixed>>, mentions: array<int, array<string, mixed>>, result_count: int}
     */
    public function buildSearchCorpus(array $results, int $maxResults = 20): array
    {
        $deduped = $this->deduplicateSearchResults($results, $maxResults);
        $corpus = [];
        $mentions = [];
        $seenMentions = [];

        foreach ($deduped as $r) {
            $title = trim($r['title'] ?? '');
            $snippet = trim($r['snippet'] ?? $r['body'] ?? '');
            $url = trim($r['url'] ?? '');
            $text = $title.' '.$snippet;
            $prices = $this->extractPrices($text);

            $corpus[] = [
                'title' => mb_substr($title, 0, 120),
                'snippet' => mb_substr($snippet, 0, 500),
                'url' => $url,
                'prices' => $prices,
                'site' => $r['site'] ?? app(SiteSelectorService::class)->detectSiteFromUrl($url),
            ];

            $candidates = array_merge(
                $this->extractProductMentionsFromText($text),
                $this->extractProductMentionsFromTitle($title),
            );
            foreach ($candidates as $name) {
                $key = strtolower($name);
                if (isset($seenMentions[$key])) {
                    $seenMentions[$key]['source_urls'][] = $url;
                    if ($prices) {
                        $seenMentions[$key]['prices'] = array_values(array_unique(
                            array_merge($seenMentions[$key]['prices'], $prices)
                        ));
                    }
                    $seenMentions[$key]['mention_count']++;

                    continue;
                }

                $seenMentions[$key] = [
                    'product_name' => $name,
                    'prices' => $prices,
                    'source_urls' => $url ? [$url] : [],
                    'mention_count' => 1,
                ];
            }
        }

        $ecommerce = $this->extractEcommerceProductTitles($deduped, null, null, $maxResults);
        foreach ($ecommerce as $item) {
            $name = trim($item['product'] ?? '');
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            $url = $item['url'] ?? '';
            if (isset($seenMentions[$key])) {
                $seenMentions[$key]['mention_count']++;
                if ($url) {
                    $seenMentions[$key]['source_urls'][] = $url;
                }
                if (! empty($item['price_bdt'])) {
                    $seenMentions[$key]['prices'][] = (int) $item['price_bdt'];
                }

                continue;
            }
            $seenMentions[$key] = [
                'product_name' => $name,
                'prices' => isset($item['price_bdt']) ? [(int) $item['price_bdt']] : [],
                'source_urls' => $url ? [$url] : [],
                'mention_count' => 2,
            ];
        }

        $mentions = [];
        foreach ($seenMentions as $mention) {
            $mention['source_urls'] = array_values(array_unique(array_filter($mention['source_urls'])));
            $mention['prices'] = array_values(array_unique($mention['prices'] ?? []));
            $mentions[] = $mention;
        }
        usort($mentions, fn ($a, $b) => ($b['mention_count'] ?? 0) <=> ($a['mention_count'] ?? 0));

        return [
            'results' => $corpus,
            'mentions' => $mentions,
            'result_count' => count($corpus),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractProductMentionsFromText(string $text): array
    {
        $names = [];

        $patterns = [
            '/\bBuy\s+(.+?)\s+Online\b/i',
            '/\bBest\s+(.+?)\s+(?:in|for)\s+Bangladesh\b/i',
            '/^\s*\d+\.\s+\*?\*?([^*\n|]{3,80})\*?\*?\s*$/m',
            '/^\s*[\*\-]\s+([A-Za-z][^.\n]{2,80})\s*$/m',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }
            foreach ($matches[1] as $raw) {
                $name = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
                $name = preg_replace('/\s*[-|].*(daraz|pickaboo|shajgoj).*$/i', '', $name) ?? $name;
                $name = trim($name);
                if ($name && ! $this->isGenericProductName($name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<int, string>
     */
    private function extractProductMentionsFromTitle(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            return [];
        }

        $names = [];
        $patterns = [
            '/^(.+?)\s*[-|]\s*(Daraz|Pickaboo|Shajgoj|Othoba)/i',
            '/\bTop\s+\d+\s+(.+?)\s+(?:in|for)\s+Bangladesh/i',
            '/\bBest\s+(.+?)\s+(?:in|for)\s+(?:Bangladesh|BD)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $m)) {
                $name = trim(preg_replace('/\s+/', ' ', $m[1]) ?? $m[1]);
                if ($name && ! $this->isGenericProductName($name)) {
                    $names[] = $name;
                }
            }
        }

        if (preg_match('#/products/[^/]+-i\d+#i', $title) || preg_match('/\bBuy\s+/i', $title)) {
            $cleaned = preg_replace('/\s*[-|].*(daraz|pickaboo|shajgoj).*$/i', '', $title) ?? $title;
            $cleaned = trim(preg_replace('/\bBuy\s+|\s+Online.*$/i', '', $cleaned) ?? $cleaned);
            if ($cleaned && ! $this->isGenericProductName($cleaned)) {
                $names[] = $cleaned;
            }
        }

        return array_values(array_unique($names));
    }

    public function isGenericProductName(string $name): bool
    {
        $lower = strtolower(trim($name));
        if (strlen($lower) < 2) {
            return true;
        }
        if ($this->isJunkListingName($name)) {
            return true;
        }
        if (in_array($lower, self::GENERIC, true)) {
            return true;
        }
        $words = preg_split('/\s+/', $lower) ?: [];
        if (count($words) <= 2) {
            foreach ($words as $w) {
                if (in_array($w, self::GENERIC, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** SEO listing titles like "2000 taka dress" or "ladies dress 500 taka". */
    public function isJunkListingName(string $name): bool
    {
        $lower = strtolower(trim($name));

        if (preg_match('/\d{2,5}\s*(taka|tk|bdt)\b|\b(taka|tk|bdt)\s*\d{2,5}\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(ladies|women|girls|mens|men|boys)\s+\w*\s*\d/i', $lower)) {
            return true;
        }
        if (preg_match('/^(dress|shirts?|pants?|shoes?)\s*(under|below|within)?\s*\d/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(under|below|within)\s+\d+/i', $lower)) {
            return true;
        }
        if (preg_match('/\bbuy\s+.+\bonline\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(trending|top selling|best selling|new products?|hot deals?|taka bundle|taka deals?|11 taka)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(bank\s*note|currency|money|coins?|notes?\s*taka|taka\s*notes?)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(best\s+sellers?|most\s+selling|online\s+shopping|shopping\s+sites?)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(collection|catalog|category|deals?\s*page|shop\s*now)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(products?|items?|deals?)\s+(20\d{2}|bangladesh)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(online\s+shopping\s+sites?|shopping\s+sites?|e-?commerce\s+sites?|marketplace)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(home decor|fashion and clothing|health and beauty|electronics and gadgets|books and educational|toys and games|pet products|kitchen appliances|digital products|fitness equipment)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\bproducts?\s+for\s+(online\s+)?business\b/i', $lower)) {
            return true;
        }
        if (preg_match('/^(earbuds?|headphones?|smartphones?|trending)\s*20\d{2}$/i', $lower)) {
            return true;
        }
        if (preg_match('/^20\d{2}\s+(earbuds?|headphones?|smartphones?)$/i', $lower)) {
            return true;
        }
        if (preg_match('/^(t-?shirts?|hoodies?|jeans?|dresses?|ethnic wear|sarees?|sandals?)$/i', $lower)) {
            return true;
        }
        if (preg_match('/\bcollection\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(e-?commerce platform|clothing in bd|fashion & clothing|fashion and clothing)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/^best\s+(women\'?s?|men\'?s?|ladies)\s+(clothing|fashion)/i', $lower)) {
            return true;
        }
        if (preg_match('/^20\d{2}\s+(ladies|women|men|fashion)$/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(outfit ideas|styling tips|budget.friendly|blog top)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/^(daraz|pickaboo|shajgoj|othoba)$/i', $lower)) {
            return true;
        }
        if (preg_match('/\bstylish\s+(women\'?s?|men\'?s?)\s+clothing\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(fusion ethnic|eco.friendly|bold colors?|fashion choices?|search analysis)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(best|buy)\s+.+\b(saree|blouse|kurti|dress|dupatta|panjabi)\b.+\b(shop|store)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(saree|blouse|kurti).+(saree|blouse|kurti|dress|dupatta|panjabi).+(shop|store)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\bshop\s*$/i', $lower)) {
            return true;
        }
        if (strlen($lower) > 75) {
            return true;
        }

        return false;
    }

    /** Require at least 2 words or a model number — filters "tops", "Daraz", "jackets". */
    public function isSpecificProductName(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || $this->isGenericProductName($name)) {
            return false;
        }
        $words = array_filter(preg_split('/\s+/', $name) ?: []);

        return count($words) >= 2 || (bool) preg_match('/\d/', $name);
    }

    /**
     * Extract real product names from Daraz/Pickaboo/Shajgoj search result titles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractEcommerceProductTitles(array $results, ?int $bmin, ?int $bmax, int $topN): array
    {
        $products = [];
        $seen = [];

        $sorted = collect($results)->sortByDesc(function ($r) {
            $url = $r['url'] ?? '';
            $title = $r['title'] ?? '';

            if (preg_match('/\b(online store|fashion hub|official website)\b/i', $title)) {
                return 0;
            }

            return match (true) {
                (bool) preg_match('#/products/[^/]+-i\d+#i', $url) => 4,
                (bool) preg_match('#\b(blog|top-|best-|trending)\b#i', $url) => 3,
                (bool) preg_match('#(daraz|pickaboo|shajgoj|othoba|rokomani)\.com#i', $url) => 2,
                default => 1,
            };
        })->values()->all();

        foreach ($sorted as $r) {
            $title = trim($r['title'] ?? '');
            $url = $r['url'] ?? '';
            if ($title === '' || preg_match('#/(tag|catalog|wow)/#i', $url)) {
                continue;
            }

            $name = $this->parseEcommerceTitle($title, $url);
            if (! $name || ! $this->isSpecificProductName($name)) {
                continue;
            }

            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }

            $text = $title.' '.($r['snippet'] ?? '');
            $prices = $this->extractPrices($text);
            $price = $prices[0] ?? null;
            if ($price && $bmax && ($price < ($bmin ?? 0) || $price > $bmax)) {
                continue;
            }

            $seen[$key] = true;
            $siteName = $r['site'] ?? app(SiteSelectorService::class)->detectSiteFromUrl($url);
            $products[] = [
                'product' => $name,
                'price' => $price,
                'price_bdt' => $price,
                'price_label' => $price ? '৳'.number_format($price) : '',
                'url' => $url,
                'site' => $siteName,
                'label' => 'HOT',
                'reason' => $price ? '৳'.number_format($price) : $siteName,
            ];

            if (count($products) >= $topN) {
                return $products;
            }
        }

        return $products;
    }

    private function parseEcommerceTitle(string $title, string $url): ?string
    {
        if (preg_match('/\b(online store|fashion hub|official website|premium fashion hub|shop now)\b/i', $title)) {
            return null;
        }

        $isProductUrl = (bool) preg_match('#/products/[^/]+-i\d+#i', $url);

        if (! $isProductUrl) {
            return null;
        }

        $isEcom = (bool) preg_match('#(daraz|pickaboo|shajgoj|othoba|ajkerdeal|rokomani)\.com#i', $url);

        if ($isProductUrl || $isEcom) {
            if (preg_match('/^(.+?)\s*[-|]\s*Daraz\.com\.bd/i', $title, $m)) {
                return $this->cleanProductTitle($m[1]);
            }
            if (preg_match('/^(.+?)\s*[-|]\s*(Pickaboo|Shajgoj|Othoba)/i', $title, $m)) {
                return $this->cleanProductTitle($m[1]);
            }
            if ($isProductUrl && ! preg_match('/^buy\s/i', $title)) {
                $cleaned = preg_replace('/\s*[-|].*(daraz|pickaboo|shajgoj).*$/i', '', $title);

                return $this->cleanProductTitle($cleaned ?? $title);
            }
        }

        if (preg_match('/\bBuy\s+(.+?)\s+Online\b/i', $title, $m) && $isProductUrl && ! preg_match('#/(tag|catalog|wow)/#i', $url)) {
            $candidate = $this->cleanProductTitle($m[1]);
            if ($candidate && strlen($candidate) >= 4 && strlen($candidate) <= 100) {
                return $candidate;
            }
        }

        return null;
    }

    private function cleanProductTitle(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $name = preg_replace('/\s+20\d{2}$/', '', $name) ?? $name;
        $name = preg_replace('/\s*:\s*Buy Online.*$/i', '', $name) ?? $name;
        $name = trim($name);

        if ($name === '' || strlen($name) < 4 || strlen($name) > 120) {
            return null;
        }

        return $name;
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$lists
     * @return array<int, array<string, mixed>>
     */
    public function mergeProductLists(int $topN, array ...$lists): array
    {
        $merged = [];
        $seen = [];

        foreach ($lists as $list) {
            foreach ($list as $item) {
                $name = trim($item['product'] ?? $item['name'] ?? '');
                if (! $name || ! $this->isSpecificProductName($name)) {
                    continue;
                }
                $key = strtolower($name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $merged[] = $item;
                if (count($merged) >= $topN) {
                    return $merged;
                }
            }
        }

        return $merged;
    }

    public function filterSpecificProducts(array $products): array
    {
        $clean = [];
        $seen = [];
        foreach ($products as $item) {
            $name = trim($item['product'] ?? $item['name'] ?? '');
            if (! $name || $this->isGenericProductName($name)) {
                continue;
            }
            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = array_merge($item, ['product' => $name]);
        }

        return $clean;
    }

    public function filterByBudget(array $products, ?int $bmin, ?int $bmax, int $topN): array
    {
        if (! $bmax && ! $bmin) {
            return array_slice($products, 0, $topN);
        }
        $bmin = $bmin ?? 0;
        $bmax = $bmax ?? 999999;
        $inBudget = [];
        $unknown = [];

        foreach ($products as $p) {
            $price = $p['price_bdt'] ?? $p['price'] ?? null;
            if ($price !== null) {
                $price = (int) $price;
                if ($price >= $bmin && $price <= $bmax) {
                    $p['price_bdt'] = $price;
                    $p['reason'] = $p['reason'] ?? '~৳'.number_format($price);
                    $inBudget[] = $p;
                }
            } else {
                $unknown[] = $p;
            }
        }

        $merged = $inBudget;
        foreach ($unknown as $p) {
            if (count($merged) >= $topN) {
                break;
            }
            $merged[] = $p;
        }

        return array_slice($merged, 0, $topN);
    }

    public function extractListingsFromResults(array $results, ?int $bmin, ?int $bmax, int $topN): array
    {
        $listings = [];
        $seen = [];
        foreach ($results as $r) {
            $text = ($r['title'] ?? '').' '.($r['snippet'] ?? '');
            $name = null;

            if (preg_match('/\bBuy\s+(.+?)\s+Online\b/i', $text, $m)) {
                $name = trim($m[1]);
            } elseif (preg_match('/\bBest\s+(.+?)\s+(?:in|for)\s+Bangladesh\b/i', $text, $m)) {
                $candidate = trim($m[1]);
                if (! preg_match('/\b(clothing|fashion|products?|platform)\b/i', $candidate)) {
                    $name = $candidate;
                }
            } elseif (preg_match('/^\d+\.\s+([A-Za-z0-9][^\n]{2,60})$/m', $r['snippet'] ?? '', $m)) {
                $name = trim($m[1]);
            }

            if ($name && ! $this->isGenericProductName($name)) {
                $key = strtolower($name);
                if (! isset($seen[$key])) {
                    $prices = $this->extractPrices($text);
                    $price = $prices[0] ?? null;
                    if (! ($price && $bmax && ($price < ($bmin ?? 0) || $price > $bmax))) {
                        $seen[$key] = true;
                        $siteName = $r['site'] ?? app(SiteSelectorService::class)->detectSiteFromUrl($r['url'] ?? '');
                        $listings[] = [
                            'product' => $name,
                            'price' => $price,
                            'price_label' => $price ? '৳'.number_format($price) : '',
                            'url' => $r['url'] ?? '',
                            'site' => $siteName,
                            'label' => 'HOT',
                            'reason' => $price ? '৳'.number_format($price) : $siteName,
                        ];
                        if (count($listings) >= $topN) {
                            return $listings;
                        }
                    }
                }
            }

            $snippet = $r['snippet'] ?? '';
            if (strlen($snippet) > 250) {
                $fromMd = $this->extractProductsFromMarkdown($snippet, $bmin, $bmax, $topN);
                $listings = $this->mergeProductLists($topN, $listings, $fromMd);
                if (count($listings) >= $topN) {
                    return $listings;
                }
            }
        }

        return $listings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractProductsFromMarkdown(string $text, ?int $bmin, ?int $bmax, int $topN): array
    {
        $products = [];
        $seen = [];

        if (preg_match_all('/^\|\s*(\d+)\s*\|\s*([^|]+?)\s*\|/m', $text, $rows, PREG_SET_ORDER)) {
            foreach ($rows as $row) {
                $name = trim($row[2]);
                if ($name === '' || preg_match('/^(product|category|---|\-+$)/i', $name)) {
                    continue;
                }
                $this->pushMarkdownProduct($name, $text, $products, $seen, $bmin, $bmax, $topN);
                if (count($products) >= $topN) {
                    return $products;
                }
            }
        }

        if (preg_match_all('/^#{1,4}\s*\d+\.\s*\*?\*?([^*\n|]+?)\*?\*?\s*$/m', $text, $headings)) {
            foreach ($headings[1] as $name) {
                $name = trim(preg_replace('/\s*\(.*\)\s*$/', '', $name) ?? $name);
                $this->pushMarkdownProduct($name, $text, $products, $seen, $bmin, $bmax, $topN);
                if (count($products) >= $topN) {
                    return $products;
                }
            }
        }

        if (preg_match_all('/^\s*[\*\-]\s+([A-Za-z][^.\n]{2,90})\.\s*$/m', $text, $bullets)) {
            foreach ($bullets[1] as $line) {
                if (preg_match('/\b(demand|industry|business|customers|easily|thriving|segments)\b/i', $line)) {
                    continue;
                }
                foreach (preg_split('/,\s*|\s+and\s+/i', $line) ?: [] as $part) {
                    $name = trim(preg_replace('/^and\s+/i', '', trim($part)) ?? trim($part));
                    $this->pushMarkdownProduct($name, $text, $products, $seen, $bmin, $bmax, $topN);
                    if (count($products) >= $topN) {
                        return $products;
                    }
                }
            }
        }

        return $products;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, bool>  $seen
     */
    private function pushMarkdownProduct(
        string $name,
        string $text,
        array &$products,
        array &$seen,
        ?int $bmin,
        ?int $bmax,
        int $topN,
    ): void {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        if ($name === '' || $this->isGenericProductName($name)) {
            return;
        }
        $key = strtolower($name);
        if (isset($seen[$key])) {
            return;
        }

        $prices = $this->extractPrices($text);
        $price = $prices[0] ?? null;
        if ($price && $bmax && ($price < ($bmin ?? 0) || $price > $bmax)) {
            return;
        }

        $seen[$key] = true;
        $products[] = [
            'product' => $name,
            'price' => $price,
            'price_bdt' => $price,
            'label' => 'HOT',
            'reason' => $price ? '৳'.number_format($price) : 'from article',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $shoppingResults
     * @return array<int, array<string, mixed>>
     */
    public function extractProductsFromAiShopping(array $shoppingResults): array
    {
        $products = [];
        foreach ($shoppingResults as $item) {
            $title = trim($item['title'] ?? '');
            if (! $title || $this->isGenericProductName($title)) {
                continue;
            }
            $price = $item['extracted_price'] ?? null;
            $products[] = [
                'product' => $title,
                'price_bdt' => $price ? (int) $price : null,
                'label' => 'HOT',
                'reason' => $item['price'] ?? ($price ? '৳'.number_format($price) : ''),
            ];
        }

        return $products;
    }

    public function extractPrices(string $text): array
    {
        $prices = [];
        if (preg_match_all('/(?:৳|Tk\.?|BDT|tk)\s*([\d,]+)|([\d,]+)\s*(?:tk|taka|bdt)\b/i', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $raw = $match[1] ?: $match[2];
                $prices[] = (int) str_replace(',', '', $raw);
            }
        }

        return $prices;
    }

    public function productSection(string $name): string
    {
        $lower = strtolower($name);

        if (preg_match('/\b(for women|women\'?s|ladies|girls?|woman)\b/i', $lower)) {
            return 'women';
        }
        if (preg_match('/\b(for men|men\'?s|for man\b|boys?)\b/i', $lower)) {
            return 'men';
        }

        $women = ['women', 'kurti', 'saree', 'leggings', 'gown', 'hijab', 'tops', 'blouse', 'palazzo', 'salwar', 'lipstick', 'sarees'];
        $men = ['panjabi', 'punjabi', 'panjabi', 'hoodie'];
        foreach ($women as $k) {
            if (str_contains($lower, $k)) {
                return 'women';
            }
        }
        foreach ($men as $k) {
            if (str_contains($lower, $k)) {
                return 'men';
            }
        }
        if (preg_match('/\b(men\'?s|boys?)\b/i', $lower)) {
            return 'men';
        }
        if (preg_match('/\b(shirt|t-shirt|t shirt|jeans)\b/i', $lower) && ! str_contains($lower, 't-shirt for women')) {
            return 'men';
        }

        return 'general';
    }

    public function matchesAudience(string $name, string $audience): bool
    {
        $lower = strtolower($name);

        if ($audience === 'women' && preg_match('/\b(panjabi|punjabi|men\'?s|for men|for man\b|boys?|jersey for man|man fashion)\b/i', $lower)) {
            return false;
        }
        if ($audience === 'women' && preg_match('/\b(jersey|world cup)\b/i', $lower) && ! preg_match('/\b(women|ladies|girls?|female)\b/i', $lower)) {
            return false;
        }
        if ($audience === 'men' && preg_match('/\b(women\'?s|for women|ladies|girls?|kurti|saree|salwar)\b/i', $lower)) {
            return false;
        }

        $section = $this->productSection($name);

        return match ($audience) {
            'women' => $section !== 'men',
            'men' => $section !== 'women',
            default => true,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public function rankBySeasonKeywords(array $products, array $seasonKeywords, int $topN): array
    {
        if (empty($products) || empty($seasonKeywords)) {
            return array_slice($products, 0, $topN);
        }

        $scored = [];
        foreach ($products as $p) {
            $name = strtolower($p['product'] ?? $p['name'] ?? '');
            $score = 0;
            foreach ($seasonKeywords as $kw) {
                $parts = array_filter(preg_split('/\s+/', strtolower($kw)) ?: []);
                foreach ($parts as $part) {
                    if (strlen($part) >= 3 && str_contains($name, $part)) {
                        $score += 2;
                    }
                }
                if (str_contains($name, strtolower($kw))) {
                    $score += 3;
                }
            }
            $scored[] = ['product' => $p, 'score' => $score];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $ranked = array_column($scored, 'product');
        $withScore = array_filter($scored, fn ($s) => $s['score'] > 0);

        if (count($withScore) >= $topN) {
            return array_slice(array_column($withScore, 'product'), 0, $topN);
        }

        return array_slice($ranked, 0, $topN);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public function filterByAudience(array $products, ?string $audience): array
    {
        if (! $audience) {
            return $products;
        }

        return array_values(array_filter(
            $products,
            fn ($p) => $this->matchesAudience($p['product'] ?? $p['name'] ?? '', $audience),
        ));
    }

    public function formatProductList(array $products, int $topN, array $searchData, string $note = ''): string
    {
        $lines = ["### 🏆 Top {$topN} Products — Bangladesh\n"];
        if (! empty($searchData['category_label'])) {
            $lines[] = '📂 **Category:** '.$searchData['category_label']."\n";
        }
        if (! empty($searchData['budget_max'])) {
            $bmax = $searchData['budget_max'];
            $bmin = $searchData['budget_min'] ?? null;
            $lines[] = $bmin
                ? '💰 **Budget:** ৳'.number_format($bmin).' – ৳'.number_format($bmax)."\n"
                : '💰 **Budget:** সর্বোচ্চ ৳'.number_format($bmax)."\n";
        }
        if ($note) {
            $lines[] = "_{$note}_\n";
        }
        if ($products) {
            $lines[] = "**Product list:**\n";
            foreach (array_slice($products, 0, $topN) as $i => $item) {
                $emoji = match ($item['label'] ?? 'MODERATE') {
                    'HOT' => '🔥', 'RISING' => '📈', default => '➡️',
                };
                $lines[] = ($i + 1).". {$emoji} **{$item['product']}**";
                if (! empty($item['reason'])) {
                    $lines[] = '   _'.$item['reason'].'_';
                }
            }
        } else {
            $lines[] = '_Specific product name পাওয়া যায়নি।_';
        }

        return implode("\n", $lines);
    }
}
