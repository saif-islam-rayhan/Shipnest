<?php

namespace App\Services\Market;

class BangladeshSeasonService
{
    /**
     * @return array{key: string, label_en: string, label_bn: string}
     */
    public function forPeriod(GooglePeriod $period): array
    {
        $month = $period->monthFrom;

        return match (true) {
            in_array($month, [11, 12, 1, 2], true) => [
                'key' => 'winter',
                'label_en' => 'winter',
                'label_bn' => 'শীত',
            ],
            in_array($month, [3, 4, 5], true) => [
                'key' => 'summer',
                'label_en' => 'summer',
                'label_bn' => 'গ্রীষ্ম',
            ],
            in_array($month, [6, 7, 8, 9], true) => [
                'key' => 'monsoon',
                'label_en' => 'monsoon',
                'label_bn' => 'বর্ষা',
            ],
            default => [
                'key' => 'autumn',
                'label_en' => 'autumn',
                'label_bn' => 'শরৎ',
            ],
        };
    }

    /**
     * DuckDuckGo queries — month/season/category only, NO product names.
     *
     * @return array<int, string>
     */
    public function buildMonthQueries(
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
    ): array {
        $season = $this->forPeriod($period);
        $label = $period->label();
        $cat = $category ? config("market.categories.{$category}.label", $category) : '';
        $budget = $this->budgetClause($budgetMin, $budgetMax);
        $queries = [];

        if ($cat) {
            $queries[] = "best selling {$cat} products Bangladesh {$label} {$season['label_en']} daraz";
            $queries[] = "top {$topN} trending {$cat} Bangladesh {$label} pickaboo shajgoj";
        } else {
            $queries[] = "top {$topN} best selling products Bangladesh {$label} {$season['label_en']} daraz";
            $queries[] = "trending products Bangladesh {$label} {$season['label_en']} ecommerce";
        }

        if ($budget) {
            $queries[] = "{$cat} {$budget} taka best selling Bangladesh {$label} daraz";
        }

        $queries[] = "most popular online shopping products Bangladesh {$label}";

        $queries[] = "top {$topN} best selling products Bangladesh {$label} 2026 blog list";
        $queries[] = "most sold products daraz Bangladesh {$season['label_en']} {$label}";

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * DuckDuckGo queries targeting real e-commerce product pages (not blogs).
     *
     * @return array<int, string>
     */
    public function buildEcommerceProductQueries(
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
    ): array {
        $season = $this->forPeriod($period);
        $label = $period->label();
        $budget = $this->budgetClause($budgetMin, $budgetMax);
        $queries = [];

        if ($category) {
            $keywords = config("market.categories.{$category}.trending_keywords", []);
            $sites = config("market.categories.{$category}.sites", ['daraz', 'pickaboo']);
            $domain = config('market.site_registry.daraz.domain', 'daraz.com.bd');

            foreach (array_slice($keywords, 0, 4) as $kw) {
                $q = "site:{$domain}/products/ {$kw} Bangladesh {$label}";
                if ($budget) {
                    $q .= " {$budget} tk";
                }
                $queries[] = $q;
            }

            foreach (array_slice($sites, 0, 2) as $site) {
                $siteDomain = config("market.site_registry.{$site}.domain", '');
                if (! $siteDomain) {
                    continue;
                }
                $catLabel = config("market.categories.{$category}.label", $category);
                $queries[] = "site:{$siteDomain} best selling {$catLabel} Bangladesh {$label}";
            }
        } else {
            $queries[] = "site:daraz.com.bd/products/ best selling Bangladesh {$label} {$season['label_en']}";
            $queries[] = "site:daraz.com.bd/products/ trending Bangladesh {$label}";
            $queries[] = "site:pickaboo.com best seller Bangladesh {$label}";
            $queries[] = "site:shajgoj.com trending Bangladesh {$label}";

            $queries[] = match ($season['key']) {
                'winter' => "site:daraz.com.bd/products/ winter jacket hoodie Bangladesh {$label}",
                'summer' => "site:daraz.com.bd/products/ fan air fryer Bangladesh {$label}",
                'monsoon' => "site:daraz.com.bd/products/ umbrella raincoat Bangladesh {$label}",
                default => "site:daraz.com.bd/products/ smartphone earbuds Bangladesh {$label}",
            };

            foreach (match ($season['key']) {
                'winter' => [
                    "site:daraz.com.bd/products/ winter jacket Bangladesh {$label}",
                    "site:daraz.com.bd/products/ hoodie Bangladesh {$label}",
                    "site:daraz.com.bd/products/ sweater Bangladesh {$label}",
                ],
                'summer' => [
                    "site:daraz.com.bd/products/ air fryer Bangladesh {$label}",
                    "site:daraz.com.bd/products/ kurti Bangladesh {$label}",
                    "site:daraz.com.bd/products/ wireless earbuds Bangladesh {$label}",
                ],
                'monsoon' => [
                    "site:daraz.com.bd/products/ umbrella Bangladesh {$label}",
                    "site:daraz.com.bd/products/ raincoat Bangladesh {$label}",
                    "site:daraz.com.bd/products/ waterproof shoes Bangladesh {$label}",
                ],
                default => [
                    "site:daraz.com.bd/products/ samsung galaxy Bangladesh {$label}",
                    "site:daraz.com.bd/products/ power bank Bangladesh {$label}",
                ],
            } as $sq) {
                $queries[] = $sq;
            }
        }

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * Open DuckDuckGo web queries — like Google search, not limited to one e-commerce site.
     *
     * @return array<int, string>
     */
    public function buildWebTrendingQueries(
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?int $budgetMin,
        ?int $budgetMax,
        ?string $audience = null,
        ?string $question = null,
    ): array {
        $season = $this->forPeriod($period);
        $label = $period->label();
        $budget = $this->budgetClause($budgetMin, $budgetMax);
        $queries = [];

        if ($question && trim($question) !== '') {
            $queries[] = trim($question);
        }

        $audienceLabel = match ($audience) {
            'women' => 'women female ladies',
            'men' => 'men male',
            default => '',
        };

        $cat = $category ? config("market.categories.{$category}.label", $category) : 'products';

        $queries[] = trim("top {$topN} trending {$audienceLabel} {$cat} products Bangladesh {$label} {$season['label_en']}");
        $queries[] = trim("best selling {$audienceLabel} {$cat} Bangladesh {$label} 2026");
        $queries[] = trim("most popular {$audienceLabel} {$cat} online shopping Bangladesh {$label}");

        if ($budget) {
            $queries[] = trim("{$audienceLabel} {$cat} {$budget} taka trending Bangladesh {$label}");
        }

        if ($category === 'fashion' && $audience === 'women') {
            $queries[] = "trending kurti saree tops Bangladesh {$label} {$season['label_en']}";
            $queries[] = "women kurti saree buy online Bangladesh {$label}";
            $queries[] = "ladies dress tops buy online Bangladesh {$label}";
        } elseif ($category === 'fashion' && $audience === 'men') {
            $queries[] = "trending panjabi shirt t-shirt Bangladesh {$label} {$season['label_en']}";
            $queries[] = "men panjabi shirt buy online Bangladesh {$label}";
        } elseif ($category === 'fashion') {
            $queries[] = "trending fashion clothing buy online Bangladesh {$label}";
        }

        $queries[] = "top {$topN} best selling products Bangladesh {$label} {$season['label_en']} ecommerce blog";

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * Season-specific search terms (for DuckDuckGo queries only — NOT output product names).
     *
     * @param  array{key: string, label_en: string, label_bn: string}  $season
     * @return array<int, string>
     */
    public function seasonSearchKeywords(?string $category, ?string $audience, array $season): array
    {
        $key = $season['key'];

        if ($category === 'fashion' && $audience === 'women') {
            return match ($key) {
                'winter' => ['winter jacket women', 'sweater women', 'hoodie women', 'wool shawl', 'fleece kurti'],
                'summer' => ['cotton kurti', 'summer dress women', 'linen saree', 'short sleeve kurti', 'sandals women'],
                'monsoon' => ['raincoat women', 'umbrella', 'waterproof jacket women', 'monsoon kurti', 'rain boots women'],
                default => ['kurti', 'saree', 'tops women'],
            };
        }

        if ($category === 'fashion' && $audience === 'men') {
            return match ($key) {
                'winter' => ['winter jacket men', 'hoodie men', 'sweater men', 'wool panjabi', 'sweatshirt men'],
                'summer' => ['cotton panjabi', 't-shirt men', 'summer shirt men', 'linen shirt'],
                'monsoon' => ['raincoat men', 'umbrella men', 'waterproof jacket men'],
                default => ['panjabi', 'formal shirt men'],
            };
        }

        if ($category === 'fashion') {
            return match ($key) {
                'winter' => ['winter jacket', 'hoodie', 'sweater', 'wool clothing'],
                'summer' => ['cotton dress', 'summer fashion', 'sandals', 'light clothing'],
                'monsoon' => ['raincoat', 'umbrella', 'waterproof shoes'],
                default => ['kurti', 'shirt', 'dress'],
            };
        }

        if ($category === 'electronics') {
            return match ($key) {
                'winter' => ['room heater', 'electric blanket', 'smartphone', 'earbuds'],
                'summer' => ['fan', 'air cooler', 'power bank', 'wireless earbuds'],
                'monsoon' => ['power bank', 'earbuds', 'phone case waterproof', 'bluetooth speaker'],
                default => ['smartphone', 'earbuds', 'power bank'],
            };
        }

        return match ($key) {
            'winter' => ['room heater', 'winter jacket', 'blanket', 'hoodie', 'hot bag'],
            'summer' => ['fan', 'air cooler', 'cotton kurti', 'sandals', 'sunscreen'],
            'monsoon' => ['umbrella', 'raincoat', 'waterproof shoes', 'power bank', 'earbuds'],
            default => ['smartphone', 'earbuds', 'kurti'],
        };
    }

    /**
     * Google-style natural language market analysis questions per month/season.
     *
     * @return array<int, string>
     */
    public function buildMarketAnalysisQueries(
        GooglePeriod $period,
        int $topN,
        ?string $category,
        ?string $audience,
        ?string $question = null,
    ): array {
        $season = $this->forPeriod($period);
        $label = $period->label();
        $monthBn = $period->labelBn();
        $queries = [];

        if ($question && trim($question) !== '') {
            $queries[] = trim($question);
        }

        $audLabel = match ($audience) {
            'women' => "women's",
            'men' => "men's",
            default => '',
        };
        $catLabel = $category ? config("market.categories.{$category}.label", $category) : 'products';

        $queries[] = "What are the top {$topN} trending {$audLabel} {$catLabel} products in Bangladesh for {$label} ({$season['label_en']} season)?";
        $queries[] = "best selling {$audLabel} {$catLabel} Bangladesh {$label} {$season['label_en']} 2026";
        $queries[] = "most popular {$catLabel} online shopping Bangladesh {$monthBn} {$season['label_en']}";

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * Product-page searches across multiple BD e-commerce sites (not Daraz-only).
     *
     * @return array<int, string>
     */
    public function buildMultiSiteProductQueries(
        GooglePeriod $period,
        ?string $category,
        ?string $audience,
    ): array {
        $label = $period->label();
        $season = $this->forPeriod($period);
        $queries = [];
        $sites = $category
            ? config("market.categories.{$category}.sites", ['daraz', 'pickaboo', 'shajgoj'])
            : ['daraz', 'pickaboo', 'shajgoj'];

        $keywords = $this->seasonSearchKeywords($category, $audience, $season);

        foreach (array_slice($sites, 0, 4) as $site) {
            $domain = config("market.site_registry.{$site}.domain", '');
            if (! $domain) {
                continue;
            }
            foreach ($keywords as $kw) {
                $queries[] = trim("site:{$domain}/products/ {$kw} Bangladesh {$label} {$season['label_en']}");
            }
        }

        return array_values(array_unique(array_filter($queries)));
    }

    private function budgetClause(?int $min, ?int $max): string
    {
        if ($min && $max) {
            return "{$min}-{$max}";
        }
        if ($max) {
            return "under {$max}";
        }

        return '';
    }
}
