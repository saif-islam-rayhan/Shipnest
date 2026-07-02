<?php

namespace App\Services\Market;

use App\Models\ChatSession;
use App\Models\Product;
use App\Services\CartService;

class DemandChatAgent
{
    private const CANCEL_KW = ['cancel', 'বাতিল', 'reset', 'নতুন', 'new', 'start over'];

    private const CART_ADD_KW = [
        'cart a add', 'add to cart', 'cart e add', 'cart-e add', 'cart add',
        'কার্টে যোগ', 'কার্টে add', 'কার্টে অ্যাড', 'কার্টে যুক্ত',
    ];

    private const PURCHASE_KW = [
        'kinte chai', 'kinbo', 'buy this', 'buy it', 'purchase this',
        'কিনতে চাই', 'এটা কিনব', 'product kinte', 'product kinbo',
        'ai product kinte', 'ei product kinte', 'eita kinbo',
    ];

    private const CONFIRM_YES = ['yes', 'হ্যাঁ', 'হ্যা', 'ha', 'haa', 'hmm yes', 'ok', 'okay', 'ঠিক আছে', 'যোগ করো', 'add koro'];

    private const CONFIRM_NO = ['no', 'না', 'na', 'nope', 'cancel', 'বাতিল'];

    public function __construct(
        private CompositeQueryParser $parser,
        private TrendingProductAgent $trendingProductAgent,
        private GoogleQaAgent $qaAgent,
        private PlatformProductAgent $platformAgent,
        private QueryIntentClassifier $intentClassifier,
        private InputParsers $inputParsers,
        private GooglePeriodParser $periodParser,
        private CartService $cartService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $selectedProduct
     * @param  array<int, array<string, mixed>>  $contextProducts
     * @return array{content: string, meta: array<string, mixed>}
     */
    public function handle(ChatSession $session, string $message, ?array $selectedProduct = null, array $contextProducts = []): array
    {
        $msg = trim($message);
        if ($msg === '') {
            return AgentResponseBuilder::make(
                "প্রশ্ন লিখুন:\n• `category fashion june 2026 500-600 tk kon product trending`\n• `turbo fan market demand kemon`",
                ['follow_ups' => AgentResponseBuilder::defaultFollowUps()],
            );
        }

        $lower = strtolower($msg);
        foreach (self::CANCEL_KW as $kw) {
            if (str_contains($lower, $kw)) {
                $this->resetSession($session);

                return AgentResponseBuilder::make(
                    '✅ Reset হয়েছে। নতুন প্রশ্ন লিখুন।',
                    ['type' => 'system', 'follow_ups' => AgentResponseBuilder::defaultFollowUps()],
                );
            }
        }

        if ($session->step === 'confirm_cart_add') {
            if ($this->isCartConfirmationReply($lower)) {
                return $this->handleCartConfirmation($session, $msg);
            }
            $session->update(['step' => 'idle', 'pending_cart_product_id' => null]);
        }

        if ($this->isCartOrPurchaseIntent($lower)) {
            return $this->handleCartAddRequest($session, $msg, $selectedProduct, $contextProducts);
        }

        if (preg_match('/^view\s+cart$/i', trim($message))) {
            return AgentResponseBuilder::make(
                '🛒 Cart দেখতে নিচের লিংক ব্যবহার করুন।',
                [
                    'type' => 'system',
                    'cart_url' => route('cart.index'),
                    'follow_ups' => ['checkout', 'watch'],
                ],
            );
        }

        if (preg_match('/^checkout$/i', trim($message))) {
            return AgentResponseBuilder::make(
                '💳 Checkout করতে cart থেকে এগিয়ে যান।',
                [
                    'type' => 'system',
                    'checkout_url' => route('checkout.index'),
                    'cart_url' => route('cart.index'),
                    'follow_ups' => ['view cart', 'watch'],
                ],
            );
        }

        if (in_array($lower, ['help', 'সাহায্য'], true)) {
            return AgentResponseBuilder::make($this->helpMessage(), [
                'follow_ups' => AgentResponseBuilder::defaultFollowUps(),
            ]);
        }

        if (! config('market.use_live_llm')) {
            $parsed = $this->parser->parse($msg);
            $intent = $this->intentClassifier->classify($msg, $parsed);
            if ($intent === QueryIntent::PLATFORM_SEARCH) {
                return $this->platformAgent->search($msg, $parsed);
            }

            if ($this->intentClassifier->isGeneralKnowledge($msg, $parsed)) {
                return AgentResponseBuilder::make(
                    '❌ **LLM বন্ধ** — সাধারণ প্রশ্নের উত্তরের জন্য `.env` এ `USE_LIVE_LLM=true` এবং `GITHUB_TOKEN` সেট করুন।\n\n'
                    .'Product search (যেমন `watch`, `kurti`) LLM ছাড়াই কাজ করবে।',
                    ['type' => 'error'],
                );
            }

            return AgentResponseBuilder::make(
                '❌ **LLM বন্ধ** — Market analysis-এর জন্য `.env` এ `USE_LIVE_LLM=true` এবং `GITHUB_TOKEN` সেট করুন।\n\n'
                .'Product search (যেমন `watch`, `kurti`) LLM ছাড়াই কাজ করবে।',
                ['type' => 'error'],
            );
        }

        $parsed = $this->parser->parse($msg);
        $intent = $this->intentClassifier->classify($msg, $parsed);

        // Product name always wins — even mid trending wizard (e.g. user types "smart watch")
        if ($intent === QueryIntent::PLATFORM_SEARCH) {
            if ($session->step !== 'idle') {
                $this->resetSession($session);
            }

            return $this->platformAgent->search($msg, $parsed);
        }

        if ($session->step !== 'idle') {
            $reply = $this->handleStep($session, $msg);
            if (is_array($reply)) {
                return $reply;
            }

            return AgentResponseBuilder::make(
                $reply,
                [
                    'type' => 'prompt',
                    'step' => $session->fresh()->step,
                    'follow_ups' => $this->stepFollowUps($session->fresh()),
                ],
            );
        }

        if ($parsed->isTrending) {
            $this->saveParsedToSession($session, $parsed);
            $reply = $this->trendingProductAgent->handle($msg, $parsed);
            $reply['meta']['thought_process'] = array_merge(
                ['Query intent: trending product (web search → GitHub Model analysis)'],
                $reply['meta']['thought_process'] ?? [],
            );

            return $reply;
        }

        if ($parsed->isComplete()) {
            $this->saveParsedToSession($session, $parsed);

            return $this->runTrending($session);
        }

        if ($intent === QueryIntent::GENERAL_QA) {
            $qaReply = $this->qaAgent->handleStructured($msg);
            $qaReply['meta']['thought_process'] = array_merge(
                ['Query intent: general Q&A'],
                $qaReply['meta']['thought_process'] ?? [],
            );

            return $qaReply;
        }

        $qaReply = $this->qaAgent->handleStructured($msg);
        $qaReply['meta']['thought_process'] = array_merge(
            ['Query intent: '.$this->intentClassifier->intentLabel($intent)],
            $qaReply['meta']['thought_process'] ?? [],
        );

        return $qaReply;
    }

    /**
     * @return string|array{content: string, meta: array<string, mixed>}
     */
    private function handleStep(ChatSession $session, string $msg): string|array
    {
        return match ($session->step) {
            'ask_category' => $this->stepCategory($session, $msg),
            'ask_budget' => $this->stepBudget($session, $msg),
            'ask_month' => $this->stepMonth($session, $msg),
            default => $this->qaAgent->handleStructured($msg),
        };
    }

    private function stepCategory(ChatSession $session, string $msg): string
    {
        $cat = $this->inputParsers->parseCategory($msg);
        if (! $cat) {
            return "❌ Category বুঝতে পারিনি।\n\n".$this->inputParsers->categoryPrompt();
        }
        $session->category = $cat;
        $session->step = ($session->budget_min === null && $session->budget_max === null) ? 'ask_budget' : 'ask_month';
        $session->save();

        if ($session->step === 'ask_budget') {
            return "✅ Category: **".config("market.categories.{$cat}.label_bn")."**\n\n".$this->inputParsers->budgetPrompt();
        }

        return "✅ Category সেট।\n\n".GooglePeriodParser::periodPrompt();
    }

    private function stepBudget(ChatSession $session, string $msg): string
    {
        if (str_contains(strtolower($msg), 'unlimited') || str_contains($msg, 'সব')) {
            $session->budget_min = null;
            $session->budget_max = null;
        } else {
            [$bmin, $bmax] = $this->inputParsers->parseBudget($msg);
            if ($bmax === null && $bmin === null) {
                return "❌ Budget বুঝতে পারিনি।\n\n".$this->inputParsers->budgetPrompt();
            }
            $session->budget_min = $bmin;
            $session->budget_max = $bmax;
        }
        $session->step = 'ask_month';
        $session->save();

        return "✅ Budget সেট।\n\n".GooglePeriodParser::periodPrompt();
    }

    /**
     * @return string|array{content: string, meta: array<string, mixed>}
     */
    private function stepMonth(ChatSession $session, string $msg): string|array
    {
        $period = $this->periodParser->parse($msg);
        if (! $period) {
            return "❌ Month বুঝতে পারিনি।\n\n".GooglePeriodParser::periodPrompt();
        }
        $this->savePeriod($session, $period);
        $session->step = 'idle';
        $session->save();

        return $this->runTrending($session);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function runTrending(ChatSession $session): array
    {
        $period = new GooglePeriod(
            $session->month_from,
            $session->month_to ?? $session->month_from,
            $session->year_from ?? (int) date('Y'),
            $session->year_to ?? $session->year_from ?? (int) date('Y'),
        );

        $parsed = new CompositeQuery(
            raw: $session->question ?? 'trending product',
            question: $session->question ?? 'trending product',
            category: $session->category,
            budgetMin: $session->budget_min,
            budgetMax: $session->budget_max,
            period: $period,
            topN: $session->top_n ?? 5,
            isTrending: true,
        );

        $reply = $this->trendingProductAgent->handle($session->question ?? 'trending product', $parsed);
        $reply['meta']['thought_process'] = array_merge(
            ['Query intent: trending product (web search → GitHub Model analysis)'],
            $reply['meta']['thought_process'] ?? [],
        );

        return $reply;
    }

    /**
     * @return array<int, string>
     */
    private function stepFollowUps(ChatSession $session): array
    {
        return match ($session->step) {
            'ask_category' => ['fashion', 'electronics', 'beauty'],
            'ask_budget' => ['500-600 tk', 'under 1000', 'unlimited'],
            'ask_month' => ['June 2026', 'last month', 'this month'],
            default => AgentResponseBuilder::defaultFollowUps($session->category),
        };
    }

    private function saveParsedToSession(ChatSession $session, CompositeQuery $parsed): void
    {
        $session->question = $parsed->question;
        $session->top_n = $parsed->topN;
        $session->category = $parsed->category;
        $session->budget_min = $parsed->budgetMin;
        $session->budget_max = $parsed->budgetMax;
        if ($parsed->period) {
            $this->savePeriod($session, $parsed->period);
        }
        $session->step = 'idle';
        $session->save();
    }

    private function savePeriod(ChatSession $session, GooglePeriod $period): void
    {
        $session->month_from = $period->monthFrom;
        $session->month_to = $period->monthTo;
        $session->year_from = $period->yearFrom;
        $session->year_to = $period->yearTo;
    }

    private function resetSession(ChatSession $session): void
    {
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
    }

    /**
     * @param  array<string, mixed>|null  $selectedProduct
     * @param  array<int, array<string, mixed>>  $contextProducts
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function handleCartAddRequest(
        ChatSession $session,
        string $message,
        ?array $selectedProduct,
        array $contextProducts = [],
    ): array {
        $product = $this->resolveProductForCart($session, $message, $selectedProduct, $contextProducts);

        if (! $product) {
            return AgentResponseBuilder::make(
                "⚠️ কোন product খুঁজে পাইনি।\n\n"
                ."উদাহরণ:\n"
                ."• `Fastrack Reflex Smart Watch cart a add koro`\n"
                ."• product **Select** করে `cart a add koro` বা `ami ei product kinte chai` লিখুন",
                [
                    'type' => 'prompt',
                    'follow_ups' => ['watch', 'earbuds', 'kurti'],
                ],
            );
        }

        $session->update([
            'step' => 'confirm_cart_add',
            'pending_cart_product_id' => $product->id,
        ]);

        $price = $product->formatted_price;

        return AgentResponseBuilder::make(
            "🛒 **{$product->name}** ({$price}) cart-এ add করতে চান?\n\n"
            .'নিশ্চিত করতে `yes` লিখুন, বাতিল করতে `no` লিখুন।',
            [
                'type' => 'confirm_cart',
                'step' => 'confirm_cart_add',
                'pending_product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price_label' => $price,
                    'image' => $product->primary_image_url,
                ],
                'follow_ups' => ['yes', 'no'],
            ],
        );
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function handleCartConfirmation(ChatSession $session, string $message): array
    {
        $lower = strtolower(trim($message));

        if ($this->matchesKeyword($lower, self::CONFIRM_NO)) {
            $session->update(['step' => 'idle', 'pending_cart_product_id' => null]);

            return AgentResponseBuilder::make(
                '❌ Cart add বাতিল হয়েছে। অন্য product select করতে পারেন।',
                ['type' => 'system', 'follow_ups' => ['watch', 'earbuds']],
            );
        }

        if (! $this->matchesKeyword($lower, self::CONFIRM_YES)) {
            return AgentResponseBuilder::make(
                'উত্তর বুঝতে পারিনি। Cart-এ add করতে `yes`, বাতিল করতে `no` লিখুন।',
                [
                    'type' => 'prompt',
                    'step' => 'confirm_cart_add',
                    'follow_ups' => ['yes', 'no'],
                ],
            );
        }

        $productId = $session->pending_cart_product_id;
        if (! $productId) {
            $session->update(['step' => 'idle']);

            return AgentResponseBuilder::make(
                '⚠️ কোন product pending নেই। আবার product select করে `cart a add koro` লিখুন।',
                ['type' => 'error'],
            );
        }

        $product = Product::query()->active()->inStock()->find($productId);
        if (! $product) {
            $session->update(['step' => 'idle', 'pending_cart_product_id' => null]);

            return AgentResponseBuilder::make(
                '❌ Product পাওয়া যায়নি বা stock শেষ।',
                ['type' => 'error'],
            );
        }

        try {
            $this->cartService->add($product->id);
        } catch (\InvalidArgumentException $e) {
            $session->update(['step' => 'idle', 'pending_cart_product_id' => null]);

            return AgentResponseBuilder::make(
                '❌ Cart-এ add করা যায়নি: '.$e->getMessage(),
                ['type' => 'error'],
            );
        }

        $session->update(['step' => 'idle', 'pending_cart_product_id' => null]);

        return AgentResponseBuilder::make(
            "✅ **{$product->name}** cart-এ add হয়েছে!\n\n"
            ."🛒 এখন **cart check** করুন এবং **checkout** করুন।",
            [
                'type' => 'cart_success',
                'follow_ups' => ['view cart', 'checkout'],
                'cart_url' => route('cart.index'),
                'checkout_url' => route('checkout.index'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $selectedProduct
     * @param  array<int, array<string, mixed>>  $contextProducts
     */
    private function resolveProductForCart(
        ChatSession $session,
        string $message,
        ?array $selectedProduct,
        array $contextProducts = [],
    ): ?Product {
        if ($selectedProduct) {
            $id = $selectedProduct['id'] ?? $selectedProduct['product_id'] ?? null;
            if ($id) {
                $product = Product::query()->active()->inStock()->find($id);
                if ($product) {
                    return $product;
                }
            }

            $selectedName = trim((string) ($selectedProduct['name'] ?? ''));
            if ($selectedName !== '') {
                $product = $this->findProductByName($selectedName);
                if ($product) {
                    return $product;
                }
            }
        }

        $nameFromMessage = $this->extractProductNameFromIntentMessage($message);
        if ($nameFromMessage !== '') {
            $product = $this->findProductByName($nameFromMessage);
            if ($product) {
                return $product;
            }
        }

        $recentProducts = $contextProducts !== [] ? $contextProducts : $this->getRecentPlatformProducts($session);

        if (count($recentProducts) === 1) {
            $id = $recentProducts[0]['id'] ?? $recentProducts[0]['product_id'] ?? null;
            if ($id) {
                $product = Product::query()->active()->inStock()->find($id);
                if ($product) {
                    return $product;
                }
            }
            $name = trim((string) ($recentProducts[0]['name'] ?? ''));
            if ($name !== '') {
                return $this->findProductByName($name);
            }
        }

        if ($selectedProduct && ! empty($selectedProduct['name']) && $recentProducts !== []) {
            return $this->matchFromRecentList((string) $selectedProduct['name'], $recentProducts);
        }

        return null;
    }

    private function findProductByName(string $name): ?Product
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) < 2) {
            return null;
        }

        $lower = strtolower($name);

        return Product::query()
            ->active()
            ->inStock()
            ->where(function ($q) use ($name, $lower) {
                $q->where('name', 'like', '%'.$name.'%')
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%'.$lower.'%']);
            })
            ->orderByRaw(
                'CASE
                    WHEN LOWER(name) = ? THEN 0
                    WHEN LOWER(name) LIKE ? THEN 1
                    WHEN LOWER(name) LIKE ? THEN 2
                    ELSE 3
                END',
                [$lower, $lower.'%', '%'.$lower.'%'],
            )
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $recentProducts
     */
    private function matchFromRecentList(string $name, array $recentProducts): ?Product
    {
        $needle = strtolower(trim($name));

        foreach ($recentProducts as $item) {
            $itemName = strtolower(trim((string) ($item['name'] ?? '')));
            if ($itemName === '' || ! str_contains($itemName, $needle) && ! str_contains($needle, $itemName)) {
                continue;
            }

            $id = $item['id'] ?? $item['product_id'] ?? null;
            if ($id) {
                $product = Product::query()->active()->inStock()->find($id);
                if ($product) {
                    return $product;
                }
            }

            return $this->findProductByName((string) ($item['name'] ?? $name));
        }

        return $this->findProductByName($name);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRecentPlatformProducts(ChatSession $session): array
    {
        $message = $session->messages()
            ->where('role', 'assistant')
            ->orderByDesc('id')
            ->get()
            ->first(fn ($m) => ($m->meta['type'] ?? '') === 'platform' && ! empty($m->meta['products']));

        return $message?->meta['products'] ?? [];
    }

    private function extractProductNameFromIntentMessage(string $message): string
    {
        $clean = trim($message);

        $suffixes = [
            '/\s*cart\s+a\s+add\s+koro?\s*$/ui',
            '/\s*cart\s+e\s+add\s+koro?\s*$/ui',
            '/\s*add\s+to\s+cart\s*$/ui',
            '/\s*কার্টে\s+(add|যোগ|অ্যাড)\s*(কর[োা]?)?\s*$/ui',
            '/\s*ami\s+(ai\s+|ei\s+|eita\s+)?product\s+kinte\s+chai\s*$/ui',
            '/\s*(ai\s+|ei\s+|eita\s+)?product\s+kinte\s+chai\s*$/ui',
            '/\s*ami\s+(ai\s+|ei\s+|eita\s+)?product\s+kinbo\s*$/ui',
            '/\s*(ai\s+|ei\s+|eita\s+)?product\s+kinbo\s*$/ui',
            '/\s*কিনতে\s+চাই\s*$/ui',
            '/\s*kinbo\s*$/ui',
            '/\s*kinte\s+chai\s*$/ui',
        ];

        foreach ($suffixes as $pattern) {
            $clean = preg_replace($pattern, '', $clean) ?? $clean;
        }

        return trim($clean);
    }

    private function isCartOrPurchaseIntent(string $lower): bool
    {
        return $this->isCartAddIntent($lower) || $this->isPurchaseIntent($lower);
    }

    private function isPurchaseIntent(string $lower): bool
    {
        foreach (self::PURCHASE_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(kinte\s+chai|kinbo|কিনতে\s+চাই)\b/ui', $lower);
    }

    private function isCartAddIntent(string $lower): bool
    {
        foreach (self::CART_ADD_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(cart|কার্ট)\b.*\b(add|যোগ|অ্যাড|kor[oa]|কর[ো])\b/ui', $lower);
    }

    private function isCartConfirmationReply(string $lower): bool
    {
        return $this->matchesKeyword($lower, self::CONFIRM_YES)
            || $this->matchesKeyword($lower, self::CONFIRM_NO);
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matchesKeyword(string $lower, array $keywords): bool
    {
        $trimmed = trim($lower);

        foreach ($keywords as $kw) {
            $k = strtolower($kw);
            if ($trimmed === $k) {
                return true;
            }
            if (mb_strlen($k) > 3 && str_contains($trimmed, $k)) {
                return true;
            }
        }

        return false;
    }

    private function helpMessage(): string
    {
        return <<<'HELP'
🔍 **ShipNest AI Agent**

**যেকোনো প্রশ্ন করুন:**
• `What is the capital of France?`
• `trending product ki?`
• `Bangladesh e-commerce market kemon?`

**Product search (ShipNest catalog):**
• `watch` / `kurti` / `earbuds`
• Product select করুন → `cart a add koro` → `yes`

`cancel` — reset
HELP;
    }
}
