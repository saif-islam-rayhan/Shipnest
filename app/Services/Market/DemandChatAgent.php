<?php

namespace App\Services\Market;

use App\Services\Market\Llm\LlmProviderManager;
use App\Models\ChatSession;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\UploadedFile;

class DemandChatAgent
{
    private const CANCEL_KW = ['cancel', 'বাতিল', 'reset', 'start over', 'নতুন করে'];

    private const CART_ADD_KW = [
        'cart a add', 'add to cart', 'cart e add', 'cart-e add', 'cart add',
        'কার্টে যোগ', 'কার্টে add', 'কার্টে অ্যাড', 'কার্টে যুক্ত',
    ];

    private const PURCHASE_KW = [
        'kinte chai', 'kinbo', 'buy this', 'buy it', 'purchase this',
        'কিনতে চাই', 'এটা কিনব', 'product kinte', 'product kinbo',
        'ai product kinte', 'ei product kinte', 'eita kinbo',
    ];

    private const ORDER_KW = [
        'order korte chai', 'order korte cai', 'order korbo', 'order koro', 'order korte',
        'order dite chai', 'place order', 'order this', 'order it',
        'অর্ডার করতে চাই', 'অর্ডার করব', 'অর্ডার দিতে চাই', 'অর্ডার করো',
    ];

    private const CONFIRM_YES = ['yes', 'হ্যাঁ', 'হ্যা', 'ha', 'haa', 'hmm yes', 'ok', 'okay', 'ঠিক আছে', 'যোগ করো', 'add koro'];

    private const CONFIRM_NO = ['no', 'না', 'na', 'nope', 'cancel', 'বাতিল'];

    public function __construct(
        private CompositeQueryParser $parser,
        private TrendingProductAgent $trendingProductAgent,
        private GoogleQaAgent $qaAgent,
        private PlatformProductAgent $platformAgent,
        private AdminProductCreateAgent $productCreateAgent,
        private AdminReviewModerationAgent $reviewModerationAgent,
        private QueryIntentClassifier $intentClassifier,
        private InputParsers $inputParsers,
        private GooglePeriodParser $periodParser,
        private CartService $cartService,
        private LlmClient $llm,
        private ImageProductSearchAgent $imageProductSearchAgent,
    ) {}

    /**
     * @param  array<string, mixed>|null  $selectedProduct
     * @param  array<int, array<string, mixed>>  $contextProducts
     * @param  array<int, UploadedFile>  $uploadedImages
     * @return array{content: string, meta: array<string, mixed>}
     */
    public function handle(
        ChatSession $session,
        string $message,
        ?array $selectedProduct = null,
        array $contextProducts = [],
        bool $adminPanel = false,
        array $uploadedImages = [],
        ?int $productId = null,
    ): array {
        $msg = trim($message);

        if ($uploadedImages !== []) {
            if ($adminPanel && $this->productCreateAgent->isProductCreateStep($session->step)) {
                return $this->productCreateAgent->handle($session, $msg, $uploadedImages, $productId);
            }

            if ($adminPanel && $this->productCreateAgent->isCreateIntent($msg)) {
                return $this->productCreateAgent->handle($session, $msg, $uploadedImages, $productId);
            }

            if ($adminPanel && $this->isImageAttachIntent($msg)) {
                $attachProductId = $productId ?? $session->last_product_id;
                if ($attachProductId) {
                    return $this->productCreateAgent->handle($session, $msg, $uploadedImages, (int) $attachProductId);
                }
            }

            return $this->imageProductSearchAgent->search($uploadedImages, $msg);
        }

        if ($msg === '') {
            return AgentResponseBuilder::make(
                $this->agentEmptyPrompt($adminPanel),
                ['follow_ups' => $this->agentFollowUps($adminPanel)],
            );
        }

        if ($uploadedImages === [] && $this->isImageLookupIntent($msg)) {
            return AgentResponseBuilder::make(
                "📷 **Product image দরকার**\n\n"
                ."কোনো product আছে কিনা বা কী product সেটা জানতে **📎 image upload** করুন, তারপর জিজ্ঞাসা করুন (যেমন `ata ki ace?`)।\n\n"
                .'Image ছাড়া শুধু text দিয়ে visual product search করা যায় না।',
                [
                    'type' => 'prompt',
                    'follow_ups' => ['watch', 'earbuds', 'trending product ki?'],
                ],
            );
        }

        $lower = strtolower($msg);
        foreach (self::CANCEL_KW as $kw) {
            if (str_contains($lower, $kw)) {
                $this->productCreateAgent->resetProductCreate($session);
                $this->reviewModerationAgent->reset($session);
                $this->resetSession($session);

                return AgentResponseBuilder::make(
                    '✅ Reset হয়েছে। নতুন প্রশ্ন লিখুন।',
                    [
                        'type' => 'system',
                        'follow_ups' => $this->agentFollowUps($adminPanel),
                    ],
                );
            }
        }

        if ($this->reviewModerationAgent->isReviewStep($session->step)) {
            if (! $adminPanel) {
                return AgentResponseBuilder::make(
                    '⚠️ Review moderation শুধুমাত্র **Admin panel** থেকে করা যায়।',
                    ['type' => 'error', 'follow_ups' => ['watch', 'trending product ki?']],
                );
            }

            return $this->reviewModerationAgent->handle($session, $msg);
        }

        if ($adminPanel && (
            $this->reviewModerationAgent->isReviewIntent($msg)
            || $this->intentClassifier->isReviewModerationIntent($msg)
        )) {
            return $this->delegateReviewModeration($session, $msg);
        }

        if ($this->productCreateAgent->isProductCreateStep($session->step)) {
            if (! $adminPanel) {
                return AgentResponseBuilder::make(
                    '⚠️ Product create flow চলছে — শুধুমাত্র **Admin panel** (`/admin/agent`) থেকে সম্পন্ন করুন।',
                    ['type' => 'error', 'follow_ups' => ['watch', 'trending product ki?']],
                );
            }

            return $this->productCreateAgent->handle($session, $msg, $uploadedImages, $productId);
        }

        if ($this->productCreateAgent->isCreateIntent($msg)
            || $this->intentClassifier->isProductCreateIntent($msg)) {
            return $this->delegateProductCreate($session, $msg, $adminPanel, $uploadedImages, $productId);
        }

        if (! $adminPanel) {
            if ($session->step === 'confirm_cart_add') {
                if ($this->isCartConfirmationReply($lower)) {
                    return $this->handleCartConfirmation($session, $msg);
                }
                $session->update(['step' => 'idle', 'pending_cart_product_id' => null]);
            }

            if ($this->isOrderOnlyMention($lower)) {
                return $this->handleOrderOptions();
            }

            if ($this->isCartOrPurchaseIntent($lower)) {
                return $this->handleCartAddRequest($session, $msg, $selectedProduct, $contextProducts);
            }

            if (preg_match('/^checkout$/i', trim($message))) {
                return $this->handleCheckoutPrompt();
            }
        }

        // Cart contents — storefront FAB + admin agent both
        if ($this->isViewCartIntent($lower)) {
            return $this->handleViewCartContents();
        }

        if (in_array($lower, ['help', 'সাহায্য'], true)) {
            return AgentResponseBuilder::make(
                $this->agentHelpMessage($adminPanel),
                ['follow_ups' => $this->agentFollowUps($adminPanel)],
            );
        }

        if ($session->step === 'idle' && $this->shouldWelcomeWithTrending($msg)) {
            return $this->handleGreeting($msg, $adminPanel);
        }

        $parsed = $this->parser->parse($msg);

        if ($this->isShipNestTrendingQuery($msg, $parsed)) {
            if ($session->step !== 'idle') {
                $this->resetSession($session);
            }

            return $this->platformAgent->trending();
        }

        if (! config('market.use_live_llm')) {
            $intent = $this->intentClassifier->classify($msg, $parsed);
            if ($intent === QueryIntent::PRODUCT_CREATE) {
                return $this->delegateProductCreate($session, $msg, $adminPanel, $uploadedImages, $productId);
            }
            if ($intent === QueryIntent::REVIEW_MODERATION) {
                return $this->delegateReviewModeration($session, $msg, $adminPanel);
            }
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

        $intent = $this->intentClassifier->classify($msg, $parsed);

        if ($intent === QueryIntent::PRODUCT_CREATE) {
            return $this->delegateProductCreate($session, $msg, $adminPanel, $uploadedImages, $productId);
        }

        if ($intent === QueryIntent::REVIEW_MODERATION) {
            return $this->delegateReviewModeration($session, $msg, $adminPanel);
        }

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
        $recentProducts = $contextProducts !== [] ? $contextProducts : $this->getRecentPlatformProducts($session);

        if ($nameFromMessage !== '') {
            $product = $this->findProductByName($nameFromMessage);
            if ($product) {
                return $product;
            }

            if ($recentProducts !== []) {
                $product = $this->matchFromRecentList($nameFromMessage, $recentProducts);
                if ($product) {
                    return $product;
                }
            }
        }

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
            ->first(function ($m) {
                if (($m->meta['type'] ?? '') !== 'platform') {
                    return false;
                }

                $meta = $m->meta ?? [];

                return ! empty($meta['products'])
                    || ! empty($meta['products_all'])
                    || ! empty($meta['trending_products_all'])
                    || ! empty($meta['trending_products']);
            });

        if (! $message) {
            return [];
        }

        $meta = $message->meta ?? [];

        return $this->dedupeProductList(array_merge(
            $meta['products'] ?? [],
            $meta['products_all'] ?? [],
            $meta['trending_products_all'] ?? [],
            $meta['trending_products'] ?? [],
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function dedupeProductList(array $products): array
    {
        $seen = [];
        $result = [];

        foreach ($products as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? $item['product_id'] ?? null;
            $key = $id ? 'id:'.$id : 'name:'.strtolower(trim((string) ($item['name'] ?? '')));

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
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

        $clean = preg_replace('/\s+(ata|eita|ei|oi|ai|eta|oita)\s*$/ui', '', $clean) ?? $clean;
        $clean = preg_replace('/^(ata|eita|ei|oi|ai|eta|oita)\s+/ui', '', $clean) ?? $clean;

        return trim($clean);
    }

    private function isOrderOnlyMention(string $lower): bool
    {
        if ($this->isCartOrPurchaseIntent($lower)) {
            return false;
        }

        foreach (self::ORDER_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(order|অর্ডার)\b.*\b(kor[teo]|dite|place)\b/ui', $lower);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function handleOrderOptions(): array
    {
        $isGuest = ! auth()->check();

        $content = "📦 **Order করতে:**\n\n"
            ."1. Product খুঁজুন (যেমন `watch`)\n"
            ."2. Cart-এ add করুন (`cart a add koro`)\n"
            ."3. Cart থেকে checkout করুন\n\n";

        if ($isGuest) {
            $content .= 'ℹ️ Cart add **login ছাড়াই** করা যায়। Checkout-এর জন্য login করতে হবে।';
        } else {
            $content .= '🛒 `view cart` বা `checkout` লিখে এগিয়ে যান।';
        }

        return AgentResponseBuilder::make($content, [
            'type' => 'system',
            'cart_url' => route('cart.index'),
            'checkout_url' => $isGuest ? route('login') : route('checkout.index'),
            'follow_ups' => $isGuest
                ? ['watch', 'view cart', 'help']
                : ['view cart', 'checkout', 'watch'],
        ]);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function handleCheckoutPrompt(): array
    {
        if (! auth()->check()) {
            return AgentResponseBuilder::make(
                "💳 Checkout করতে **login** করতে হবে।\n\n"
                .'Cart add login ছাড়াই করা যায় — আগে product cart-এ যোগ করুন, তারপর login করে checkout করুন।',
                [
                    'type' => 'system',
                    'cart_url' => route('cart.index'),
                    'checkout_url' => route('login'),
                    'follow_ups' => ['watch', 'view cart', 'help'],
                ],
            );
        }

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

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function handleViewCartContents(): array
    {
        $cart = $this->cartService->getCart(auth()->user());
        $cart->loadMissing(['items.product', 'items.variant']);
        $items = $cart->items;
        $isGuest = ! auth()->check();
        $checkoutUrl = $isGuest ? route('login') : route('checkout.index');

        if ($items->isEmpty()) {
            return AgentResponseBuilder::make(
                "🛒 আপনার cart-এ এখন **কোনো product add নেই**।\n\n"
                ."Product add করতে:\n"
                ."• Product-এর **নাম** বলুন (যেমন `watch`), তারপর `cart a add koro`\n"
                ."• অথবা product card-এ **Add to cart** এ click করুন",
                [
                    'type' => 'cart_contents',
                    'cart_url' => route('cart.index'),
                    'follow_ups' => ['watch', 'earbuds', 'trending product ki?'],
                    'show_content' => true,
                ],
            );
        }

        $totals = $this->cartService->getTotals($cart);
        $symbol = config('shipnest.currency_symbol', '৳');
        $lines = [];
        $n = 1;

        foreach ($items as $item) {
            $name = $item->product?->name ?? 'Product';
            $variant = $item->variant?->name;
            if ($variant && strcasecmp($variant, 'Default') !== 0) {
                $name .= " ({$variant})";
            }

            $qty = (int) $item->quantity;
            $unit = $item->formatted_price;
            $line = $item->formatted_line_total;
            $lines[] = "{$n}. **{$name}** × {$qty} — {$unit} (মোট {$line})";
            $n++;
        }

        $subtotal = $symbol.number_format((float) $totals['subtotal'], 2);
        $total = $symbol.number_format((float) $totals['total'], 2);
        $count = (int) $totals['item_count'];

        $content = "✅ আপনার cart-এ **{$count}টি product** add আছে:\n\n"
            .implode("\n", $lines)
            ."\n\n**Subtotal:** {$subtotal}";

        if ((float) $totals['discount'] > 0) {
            $discount = $symbol.number_format((float) $totals['discount'], 2);
            $content .= "\n**Discount:** -{$discount}";
        }

        $content .= "\n**Total:** {$total}\n\n"
            .'Checkout করতে নিচের **Checkout** বাটনে click করুন।';

        return AgentResponseBuilder::make($content, [
            'type' => 'cart_contents',
            'cart_url' => route('cart.index'),
            'checkout_url' => $checkoutUrl,
            'follow_ups' => ['checkout', 'watch'],
            'show_content' => true,
        ]);
    }

    private function isViewCartIntent(string $lower): bool
    {
        if ($this->isCartAddIntent($lower) || $this->isPurchaseIntent($lower)) {
            return false;
        }

        $trimmed = trim(preg_replace('/[?؟!.]+$/u', '', $lower) ?? $lower);

        if (preg_match('/^(view\s+cart|show\s+cart|see\s+cart|my\s+cart|cart|কার্ট)$/ui', $trimmed)) {
            return true;
        }

        $patterns = [
            '/\b(view|show|see|check|open)\s+(my\s+)?cart\b/ui',
            '/\bwhat(\'s|s|\s+is)?\s+in\s+(my\s+)?cart\b/ui',
            '/\b(my\s+)?cart\s+(items?|contents?|list)\b/ui',
            '/\bamar\s+cart\b/ui',
            '/\bcart\s*(e|a|te)?\s*(ki|kii|ki\s+ki)\b/ui',
            '/\bcart\s*(e|a|te)?\s*(ki|kii)?\s*(ace|ase|ache|acha|aso)\b/ui',
            '/\bcart\s+(dekho|dekhte|dekhai|dekhao|bolo|bol)\b/ui',
            '/কার্টে\s*(কি|কী)/u',
            '/আমার\s*কার্ট/u',
            '/কার্ট\s*(দেখ|বল)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed) || preg_match($pattern, $lower)) {
                return true;
            }
        }

        return false;
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

    private function isImageAttachIntent(string $message): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        $keywords = [
            'add image', 'upload image', 'attach image', 'image add', 'image upload',
            'ছবি যোগ', 'ছবি দাও', 'ইমেজ যোগ', 'image দাও', 'photo add',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function isImageLookupIntent(string $message): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        if ($this->productCreateAgent->isCreateIntent($message)) {
            return false;
        }

        $patterns = [
            '/\b(ata|eita|ei|ai|eta)\b.*\b(ache|ase|ki|kina|ache\s*kina)\b/ui',
            '/\b(ata|eita)\s+ki\b/ui',
            '/\b(ki\s+ache|ache\s+ki|ki\s+ase)\b/ui',
            '/\b(ei|ai)\s+product\b/ui',
            '/\bproduct\s+ta\s+(ache|ase|ki)\b/ui',
            '/\b(এটা|আছে\s*কি|কি\s*আছে|এই\s*প্রোডাক্ট)\b/u',
            '/\b(identify|find)\s+(this\s+)?product\b/i',
            '/\bwhat\s+is\s+this\b/i',
            '/\bwhat\s+product\s+is\s+this\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower) || preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    private function agentEmptyPrompt(bool $adminPanel): string
    {
        $cartHint = $adminPanel ? '' : "\n• Product select → `cart a add koro` — cart-এ যোগ";
        $reviewHint = $adminPanel ? "\n• `pending reviews` — Approve / Reject\n• `new reviews` — agent-এর Positive/Negative summary" : '';
        $name = $this->agentName();

        return <<<PROMPT
🛠️ **{$name}**

সরাসরি কাজ করুন:
• `create product` — নতুন product তৈরি{$reviewHint}
• 📷 Product image upload — catalog-এ আছে কিনা খুঁজুন
• `watch` / `kurti` — catalog search
• `trending product ki?` — ShipNest trending + cart{$cartHint}

`help` — সব command দেখুন
PROMPT;
    }

    private function agentHelpMessage(bool $adminPanel): string
    {
        $cartSection = $adminPanel ? '' : <<<'CART'

**Cart (ShipNest catalog):**
• Product select করুন → `cart a add koro` → `yes`
• `amar cart e ki ki ace` / `view cart` — cart-এর product list
• `checkout`
CART;

        $reviewSection = $adminPanel ? <<<'REVIEW'

**Review moderation:**
• Review submit → agent text + image (যদি থাকে) analyze করে Positive/Negative বলে + notification
• `new reviews` — agent inbox summary
• `pending reviews` — একটা একটা করে **Approve** / **Reject**
REVIEW : '';

        $name = $this->agentName();

        return <<<HELP
🛠️ **{$name}**

**Product create:**
• `create product`
• `create product: merchant: Shop, name: Smart Watch, category: Electronics, price: 2500, stock: 50`
• `attributes: Color: Black, Size: M`
{$reviewSection}
**Catalog & research:**
• 📷 Product image upload — catalog search (আছে কিনা + related products)
• `watch` / `earbuds` — ShipNest catalog search
• `trending product ki?` — ShipNest trending products
• `category fashion june 2026 kon product trending`{$cartSection}

`cancel` — reset
HELP;
    }

    /**
     * @return array<int, string>
     */
    private function agentFollowUps(bool $adminPanel = false): array
    {
        if ($adminPanel) {
            return [
                'pending reviews',
                'new reviews',
                'create product',
            ];
        }

        return [
            'create product',
            'watch',
            'earbuds',
            'trending product ki?',
        ];
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function handleGreeting(string $msg, bool $adminPanel): array
    {
        $trending = $this->platformAgent->trending();
        [$greeting, $usedLlm] = $this->buildGreetingReply($msg, $adminPanel);
        $hasProducts = ! empty($trending['meta']['products']);

        $trending['content'] = $hasProducts
            ? $greeting
            : $greeting."\n\n".$trending['content'];
        $trending['meta']['greeting'] = true;
        $trending['meta']['show_content'] = true;
        $trending['meta']['catalog_mode'] = 'trending';
        $trending['meta']['thought_process'] = array_merge(
            [
                $usedLlm
                    ? 'Conversational message → LLM reply + ShipNest trending catalog'
                    : 'Conversational message → welcome reply + ShipNest trending catalog',
            ],
            $trending['meta']['thought_process'] ?? [],
        );

        return $trending;
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function buildGreetingReply(string $msg, bool $adminPanel): array
    {
        if (app(LlmProviderManager::class)->isReady()) {
            try {
                return [$this->generateLlmGreetingReply($msg, $adminPanel), true];
            } catch (\Throwable) {
                // fall back to template reply
            }
        }

        return [$this->greetingMessage($msg, $adminPanel), false];
    }

    private function generateLlmGreetingReply(string $msg, bool $adminPanel): string
    {
        $agentName = $this->agentName();
        $siteName = config('shipnest.name', 'ShipNest');
        $systemPrompt = <<<PROMPT
You are {$agentName}, a warm and helpful ecommerce assistant for Bangladesh.
The user sent a casual or greeting message — NOT a product search.

Reply naturally in Bangla mixed with English (2-4 short sentences):
- Respond directly to what they said (if they ask how you are, answer warmly first).
- Briefly mention {$siteName} is a trusted ecommerce marketplace where they can shop easily.
- Do NOT search for products. Do NOT say "no products found". Do NOT list product names.
- Do NOT use numbered lists, bullet points, or demo/example phrases.
- Keep it conversational like a real chat assistant.
PROMPT;

        $body = trim($this->llm->chat(
            config('market.model_google_search'),
            $systemPrompt,
            'User message: '.trim($msg),
            temperature: 0.5,
        ));

        if ($body === '') {
            throw new \RuntimeException('Empty LLM greeting reply');
        }

        $cartHint = $adminPanel
            ? ''
            : "\n\n🛒 Product পছন্দ হলে select করে `cart a add koro` লিখুন — **login লাগবে না**।";

        return $body.$cartHint."\n\n🔥 নিচে কিছু **Trending Products** দেখুন:";
    }

    private function greetingMessage(string $msg, bool $adminPanel): string
    {
        $original = trim($msg);
        $normalized = $this->normalizeChatMessage($msg);
        $agentName = $this->agentName();
        $siteName = config('shipnest.name', 'ShipNest');

        $opening = match (true) {
            in_array($normalized, ['hy', 'hii', 'hiii'], true) => '👋 **হ্যাই!** `'.$original.'` — '.$siteName.'-এ স্বাগতম!',
            in_array($normalized, ['hi', 'hey', 'hlo', 'yo', 'sup'], true) => '👋 **Hi!** `'.$original.'` পেয়েছি — কীভাবে সাহায্য করব?',
            in_array($normalized, ['hello', 'helo', 'halo', 'hola'], true) => '👋 **Hello!** `'.$original.'` — '.$agentName.' ready!',
            $normalized === 'হ্যালো' => '👋 **হ্যালো!** আপনাকে পেয়ে ভালো লাগলো।',
            $normalized === 'হাই' => '👋 **হাই!** '.$siteName.'-এ আপনাকে স্বাগতম।',
            str_contains($normalized, 'নমস্কার') => '🙏 **নমস্কার!** '.$siteName.'-এ আপনাকে স্বাগতম।',
            str_contains($normalized, 'assalamu') || str_contains($normalized, 'salam alaikum') => '🙏 **ওয়ালাইকুম আসসালাম ওয়া রহমাতুল্লাহি ওয়া বারাকাতুহু!**',
            str_contains($normalized, 'salam') || str_contains($normalized, 'salaam') || str_contains($normalized, 'আসসালাম') => '🙏 **ওয়ালাইকুম আসসালাম!** '.$agentName.'-তে স্বাগতম।',
            str_contains($normalized, 'good night') => '🌙 **Good night!** '.$siteName.'-এ আপনাকে স্বাগতম।',
            str_contains($normalized, 'good morning') => '🌅 **Good morning!** '.$siteName.'-এ আপনাকে স্বাগতম।',
            str_contains($normalized, 'good afternoon') => '☀️ **Good afternoon!** '.$siteName.'-এ আপনাকে স্বাগতম।',
            str_contains($normalized, 'good evening') => '🌆 **Good evening!** '.$siteName.'-এ আপনাকে স্বাগতম।',
            str_contains($normalized, 'kemon acho') || str_contains($normalized, 'kemon achen') || str_contains($normalized, 'kemon aso')
                || preg_match('/\bkemon\s+a[csz]o\b/ui', $normalized) => '👋 **ভালো আছি, ধন্যবাদ জিজ্ঞাসা করার জন্য!** `'.$original.'`',
            str_contains($normalized, 'ki obostha') || str_contains($normalized, 'ki obosta') => '🙂 **সব ঠিক আছে!** আপনার কী অবস্থা?',
            str_contains($normalized, 'ki khobor') || str_contains($normalized, 'ki khabar') => '🙂 **আলহামদুলিল্লাহ, ভালো!** আপনার কী খবর?',
            str_contains($normalized, 'thank you') || str_contains($normalized, 'thanks') || str_contains($normalized, 'ধন্যবাদ') => '😊 **আপনাকেও ধন্যবাদ!** `'.$original.'`',
            default => '👋 আপনি লিখেছেন: **«'.$original.'»** — '.$siteName.'-এ স্বাগতম!',
        };

        $trust = '**'.$siteName.'** একটি **বিশ্বস্ত ecommerce marketplace** — এখানে quality product, সঠিক দাম, verified seller এবং সহজ shopping experience পাবেন। যা যা চান, সব এক জায়গায়।';

        $cartHint = $adminPanel
            ? ''
            : "\n\n🛒 Product select করে `cart a add koro` লিখলে **login ছাড়াই** cart-এ যোগ করতে পারবেন।";

        return $opening."\n\n".$trust.$cartHint."\n\n🔥 আশা করি নিচের **Trending Products** আপনার পছন্দ হবে:";
    }

    private function shouldWelcomeWithTrending(string $msg): bool
    {
        return $this->intentClassifier->isConversationalPhrase($msg);
    }

    private function normalizeChatMessage(string $msg): string
    {
        $normalized = mb_strtolower(trim($msg));
        $normalized = rtrim($normalized, '!?.।');
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    private function isShipNestTrendingQuery(string $message, CompositeQuery $parsed): bool
    {
        if ($parsed->category || $parsed->period || $parsed->budgetMin !== null || $parsed->budgetMax !== null) {
            return false;
        }

        $normalized = strtolower(trim($message));
        $normalized = rtrim($normalized, '?');
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if (preg_match('/\b(shipnest|শিপনেস্ট)\b/ui', $normalized) && preg_match('/\btrending\b/ui', $normalized)) {
            return true;
        }

        return (bool) preg_match(
            '/^(trending\s+product(s)?(\s+ki)?|ট্রেন্ডিং\s+প্রোডাক্ট(\s+কি)?)$/ui',
            $normalized,
        );
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedImages
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function delegateProductCreate(
        ChatSession $session,
        string $msg,
        bool $adminPanel,
        array $uploadedImages = [],
        ?int $productId = null,
    ): array {
        if (! $adminPanel) {
            return AgentResponseBuilder::make(
                '⚠️ Product create শুধুমাত্র **Admin panel** থেকে করা যায়।\n\n'
                .'Admin → Agent এ গিয়ে `create product` লিখুন।',
                ['type' => 'error', 'follow_ups' => ['watch', 'trending product ki?']],
            );
        }

        if ($session->step !== 'idle' && ! $this->productCreateAgent->isProductCreateStep($session->step)) {
            $this->reviewModerationAgent->reset($session);
            $this->resetSession($session);
        }

        return $this->productCreateAgent->handle($session, $msg, $uploadedImages, $productId);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function delegateReviewModeration(
        ChatSession $session,
        string $msg,
        bool $adminPanel = true,
    ): array {
        if (! $adminPanel) {
            return AgentResponseBuilder::make(
                '⚠️ Review moderation শুধুমাত্র **Admin panel** থেকে করা যায়।',
                ['type' => 'error', 'follow_ups' => ['watch', 'trending product ki?']],
            );
        }

        if ($session->step !== 'idle' && ! $this->reviewModerationAgent->isReviewStep($session->step)) {
            $this->productCreateAgent->resetProductCreate($session);
            $this->resetSession($session);
        }

        return $this->reviewModerationAgent->handle($session, $msg);
    }

    private function agentName(): string
    {
        return (string) config('shipnest.agent.name', 'ShipNest AI');
    }
}
