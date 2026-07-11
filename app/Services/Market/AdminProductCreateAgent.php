<?php

namespace App\Services\Market;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChatSession;
use App\Models\Merchant;
use App\Models\Product;
use App\Services\Merchant\MerchantProductService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductCreateAgent
{
    private const CREATE_KW = [
        'create product', 'add product', 'new product', 'product create', 'product add',
        'প্রোডাক্ট তৈরি', 'পণ্য তৈরি', 'নতুন প্রোডাক্ট', 'product add koro', 'product create koro',
    ];

    private const SKIP_KW = ['skip', 'none', 'no', 'না', 'বাদ', 'pass', 'next'];

    private const CONFIRM_YES = ['yes', 'হ্যাঁ', 'হ্যা', 'ha', 'haa', 'ok', 'okay', 'ঠিক আছে', 'confirm', 'publish', 'তৈরি করো', 'তৈরি'];

    private const CONFIRM_NO = ['no', 'না', 'cancel', 'বাতিল', 'edit', 'back'];

    public function __construct(
        private readonly ProductCreateParser $parser,
        private readonly MerchantProductService $productService,
        private readonly LlmClient $llm,
    ) {}

    public function isCreateIntent(string $message): bool
    {
        $lower = strtolower(trim($message));

        foreach (self::CREATE_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(create|add|তৈরি)\b.*\b(product|প্রোডাক্ট|পণ্য)\b/ui', $message);
    }

    public function isProductCreateStep(string $step): bool
    {
        return str_starts_with($step, 'pc_');
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedImages
     * @return array{content: string, meta: array<string, mixed>}
     */
    public function handle(ChatSession $session, string $message, array $uploadedImages = [], ?int $productId = null): array
    {
        $msg = trim($message);

        if ($uploadedImages !== []) {
            $imageResponse = $this->handleUploadedImages($session, $msg, $uploadedImages, $productId);
            if ($imageResponse !== null) {
                return $imageResponse;
            }
        }

        if ($msg === '') {
            return AgentResponseBuilder::make(
                'মেসেজ লিখুন অথবা product image upload করুন।',
                ['type' => 'product_create', 'follow_ups' => self::exampleFollowUps()],
            );
        }

        $lower = strtolower($msg);

        if ($this->matchesKeyword($lower, self::CONFIRM_NO) && $session->step === 'pc_confirm') {
            $session->update(['step' => 'idle', 'draft_product' => null]);

            return AgentResponseBuilder::make(
                '❌ Product create বাতিল হয়েছে। আবার শুরু করতে `create product` লিখুন।',
                [
                    'type' => 'product_create',
                    'follow_ups' => self::exampleFollowUps(),
                ],
            );
        }

        if ($session->step === 'pc_confirm') {
            return $this->handleConfirm($session, $msg);
        }

        if ($session->step !== 'idle' && $this->isProductCreateStep($session->step)) {
            return $this->handleStep($session, $msg);
        }

        if ($this->isCreateIntent($msg)) {
            return $this->startOrQuickCreate($session, $msg);
        }

        return AgentResponseBuilder::make(
            'Product create শুরু করতে `create product` লিখুন।',
            ['type' => 'product_create', 'follow_ups' => self::exampleFollowUps()],
        );
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function startOrQuickCreate(ChatSession $session, string $message): array
    {
        $parsed = $this->parser->parse($message);
        if (app(\App\Services\Market\Llm\LlmProviderManager::class)->isReady()) {
            $parsed = array_merge($parsed, $this->parseWithLlm($message));
        }

        $draft = $this->mergeDraft([], $parsed);
        $resolved = $this->resolveReferences($draft);

        if ($this->hasRequiredFields($resolved)) {
            $session->update([
                'step' => 'pc_confirm',
                'draft_product' => $resolved,
            ]);

            return $this->confirmPrompt($session->fresh());
        }

        $session->update([
            'step' => $this->nextMissingStep($resolved),
            'draft_product' => $resolved,
        ]);

        $merchantNotFound = ! empty($resolved['merchant']) && empty($resolved['merchant_id']);

        return $this->promptForStep(
            $session->fresh(),
            showFullGuide: $this->isBareCreateMessage($message),
            merchantNotFound: $merchantNotFound,
        );
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function handleStep(ChatSession $session, string $message): array
    {
        if ($this->isSkipMessage($message) && in_array($session->step, ['pc_attributes', 'pc_extras'], true)) {
            $nextStep = $this->stepAfterSkip($session->step);
            $session->update(['step' => $nextStep]);
            $session = $session->fresh();

            if ($session->step === 'pc_confirm') {
                return $this->confirmPrompt($session);
            }

            return $this->promptForStep($session);
        }

        $parsed = $this->parser->parse($message);
        if (app(\App\Services\Market\Llm\LlmProviderManager::class)->isReady() && count($parsed) < 2) {
            $parsed = array_merge($parsed, $this->parseWithLlm($message));
        }

        $draft = $this->mergeDraft($session->draft_product ?? [], $parsed);
        $draft = $this->resolveReferences($draft);
        $draft = $this->mergeDraft($draft, []);

        if ($session->step === 'pc_merchant' && empty($draft['merchant_id'])) {
            $needle = ! empty($draft['merchant']) ? (string) $draft['merchant'] : $message;
            $merchant = $this->resolveMerchant($needle);
            if ($merchant) {
                $draft['merchant_id'] = $merchant->id;
                $draft['merchant'] = $merchant->shop_name;
            }
        }

        if (empty($draft['category_id'])) {
            $categoryNeedle = (string) ($draft['category'] ?? $message);
            $category = $this->resolveCategory($categoryNeedle);
            if ($category) {
                $draft['category_id'] = $category->id;
                $draft['category'] = $category->name;
            }
        }

        if ($session->step === 'pc_basic') {
            if (empty($draft['name']) && ! str_contains($message, ':')) {
                $draft['name'] = $message;
            }
            if (empty($draft['category_id'])) {
                $category = $this->resolveCategory($message);
                if ($category) {
                    $draft['category_id'] = $category->id;
                    $draft['category'] = $category->name;
                }
            }
            if (empty($draft['sku'])) {
                $draft['sku'] = $this->generateSku($draft['name'] ?? 'product');
            }
        }

        if ($session->step === 'pc_pricing' && empty($draft['variants'])) {
            if (preg_match('/([\d,]+(?:\.\d+)?)/', $message, $m)) {
                $draft['variants'] = [[
                    'name' => 'Default',
                    'sku' => $draft['sku'] ?? '',
                    'price' => (float) str_replace(',', '', $m[1]),
                    'compare_price' => null,
                    'stock' => 0,
                    'weight' => null,
                ]];
            }
        }

        $session->update([
            'draft_product' => $draft,
            'step' => $this->nextMissingStep($draft, $session->step),
        ]);

        $session = $session->fresh();

        if ($session->step === 'pc_confirm') {
            return $this->confirmPrompt($session);
        }

        $merchantNotFound = $session->step === 'pc_merchant'
            && empty($draft['merchant_id'])
            && trim($message) !== ''
            && ! $this->isSkipMessage($message);

        return $this->promptForStep($session, merchantNotFound: $merchantNotFound);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function handleConfirm(ChatSession $session, string $message): array
    {
        $lower = strtolower(trim($message));

        if (! $this->matchesKeyword($lower, self::CONFIRM_YES)) {
            return AgentResponseBuilder::make(
                "উত্তর বুঝতে পারিনি। Product তৈরি করতে `yes` লিখুন, বাতিল করতে `no` বা `cancel`।\n\n".$this->formatDraftSummary($session->draft_product ?? []),
                [
                    'type' => 'product_create',
                    'step' => 'pc_confirm',
                    'draft_product' => $session->draft_product,
                    'follow_ups' => ['yes', 'no'],
                ],
            );
        }

        return $this->createProduct($session);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function createProduct(ChatSession $session): array
    {
        $draft = $this->resolveReferences($session->draft_product ?? []);
        $errors = $this->validateDraft($draft);

        if ($errors !== []) {
            $step = $this->nextMissingStep($draft);

            return AgentResponseBuilder::make(
                "❌ Product তৈরি করা যায়নি:\n\n"
                .implode("\n", array_map(fn ($e) => '• '.$e, $errors))
                ."\n\n".$this->missingFieldPrompt($draft, $step),
                [
                    'type' => 'product_create',
                    'step' => $step,
                    'draft_product' => $draft,
                    'follow_ups' => $this->followUpsForStep($step),
                ],
            );
        }

        $merchant = Merchant::query()->findOrFail($draft['merchant_id']);
        $publish = $draft['publish'] ?? true;
        $data = [
            'category_id' => $draft['category_id'],
            'brand_id' => $draft['brand_id'] ?? null,
            'name' => $draft['name'],
            'sku' => $draft['sku'],
            'short_description' => $draft['short_description'] ?? null,
            'description' => $draft['description'] ?? null,
            'meta_title' => $draft['meta_title'] ?? null,
            'meta_description' => $draft['meta_description'] ?? null,
            'tags' => $draft['tags'] ?? null,
            'status' => $publish ? ProductStatus::Active->value : ProductStatus::Draft->value,
        ];

        try {
            $variants = $this->ensureVariantSkus($draft['variants'], (string) $draft['sku']);

            $product = $this->productService->create(
                $merchant,
                $data,
                $variants,
                $draft['attributes'] ?? [],
                [],
                null,
                $draft['image_urls'] ?? [],
            );
        } catch (\Throwable $e) {
            report($e);

            return AgentResponseBuilder::make(
                "❌ Product তৈরি করা যায়নি: {$e->getMessage()}\n\n"
                .$this->formatDraftSummary($draft)
                ."\n\nঠিক করে আবার `yes` লিখুন।",
                [
                    'type' => 'product_create',
                    'step' => 'pc_confirm',
                    'draft_product' => $draft,
                    'follow_ups' => ['yes', 'no'],
                ],
            );
        }

        if (! empty($draft['pending_image_paths'])) {
            $product = $this->productService->attachStoredPaths($product, $draft['pending_image_paths']);
        }

        $product->update([
            'approval_status' => $draft['approval_status'] ?? 'approved',
            'is_featured' => (bool) ($draft['is_featured'] ?? false),
        ]);

        $this->resetSessionAfterCreate($session, $product->id);

        $editUrl = route('admin.products.edit', $product);
        $storeUrl = route('products.show', $product->slug);

        return AgentResponseBuilder::make(
            "✅ **{$product->name}** সফলভাবে তৈরি হয়েছে!\n\n"
            ."- SKU: `{$product->sku}`\n"
            .'- Price: '.$product->formatted_price."\n"
            .'- Merchant: '.($merchant->shop_name)."\n"
            ."- Status: ".($publish ? 'Published' : 'Draft')."\n\n"
            ."[Storefront-এ দেখুন →]({$storeUrl})",
            [
                'type' => 'product_create_success',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price_label' => $product->formatted_price,
                    'image' => $product->primary_image_url,
                    'store_url' => $storeUrl,
                    'admin_url' => $editUrl,
                ],
                'follow_ups' => ['create product', 'watch', 'trending product ki?'],
            ],
        );
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function promptForStep(ChatSession $session, bool $showFullGuide = false, bool $merchantNotFound = false): array
    {
        $draft = $session->draft_product ?? [];
        $step = $session->step;

        $parts = ["🛍️ **Product Create**\n"];

        if ($showFullGuide || ($step === 'pc_merchant' && $this->isDraftNearlyEmpty($draft))) {
            $parts[] = $this->formatFieldsGuide();
        } else {
            $parts[] = $this->formatProgressChecklist($draft);
        }

        if ($merchantNotFound) {
            $parts[] = "⚠️ Merchant খুঁজে পাওয়া যায়নি। নিচের তালিকা থেকে সঠিক নাম দিন।";
        }

        $parts[] = $this->missingFieldPrompt($draft, $step);

        $content = implode("\n\n", array_filter($parts));

        return AgentResponseBuilder::make($content, [
            'type' => 'product_create',
            'step' => $step,
            'draft_product' => $draft,
            'follow_ups' => $this->followUpsForStep($step),
        ]);
    }

    private function isBareCreateMessage(string $message): bool
    {
        $parsed = $this->parser->parse($message);
        if ($parsed !== []) {
            return false;
        }

        $lower = strtolower(trim(rtrim(trim($message), '?')));

        foreach (self::CREATE_KW as $kw) {
            if ($lower === strtolower($kw)) {
                return true;
            }
        }

        return (bool) preg_match('/^(create|add|তৈরি)\s+(product|প্রোডাক্ট|পণ্য)$/ui', $lower);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function isDraftNearlyEmpty(array $draft): bool
    {
        return empty($draft['merchant_id'])
            && empty($draft['name'])
            && empty($draft['category_id'])
            && empty($draft['variants']);
    }

    private function formatFieldsGuide(): string
    {
        $sample = $this->sampleMerchantLabel();

        return "**Product তৈরিতে যা লাগবে:**\n\n"
            ."✅ **বাধ্যতামূলক:**\n"
            ."• `merchant` — কোন দোকান/merchant\n"
            ."• `name` — product নাম\n"
            ."• `category` — ক্যাটাগরি\n"
            ."• `price` — বিক্রয় মূল্য (৳)\n"
            ."• `stock` — স্টক সংখ্যা\n\n"
            ."ℹ️ **ঐচ্ছিক** (পরে দিতে পারেন, বাদ দিতে `skip`):\n"
            ."• `sku` — না দিলে auto তৈরি হবে\n"
            ."• `brand`, `compare_price`, `attributes`, `description`, `image`, `tags`, `featured`, `publish`\n\n"
            ."**দ্রুত উদাহরণ (এক লাইনে):**\n"
            ."`create product: merchant: {$sample}, name: Smart Watch, category: Electronics, price: 2500, stock: 50`";
    }

    private function sampleMerchantLabel(): string
    {
        return Merchant::query()
            ->where('status', 'active')
            ->orderBy('shop_name')
            ->value('shop_name') ?: 'TechZone BD';
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function formatProgressChecklist(array $draft): string
    {
        $items = [
            'Merchant' => ! empty($draft['merchant_id']),
            'Name' => ! empty($draft['name']),
            'Category' => ! empty($draft['category_id']),
            'Price & stock' => ! empty($draft['variants']),
        ];

        $lines = collect($items)->map(fn (bool $done, string $label) => ($done ? '✅' : '⬜')." {$label}")->values()->all();

        return "**অগ্রগতি:**\n".implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function missingFieldPrompt(array $draft, string $step): string
    {
        if (empty($draft['merchant_id'])) {
            return "👉 **Merchant** দিন — কোন দোকানের product?\n\n"
                .$this->listMerchants()
                ."\n\nউদাহরণ: `merchant: {$this->sampleMerchantLabel()}`";
        }

        if (empty($draft['name'])) {
            return "👉 **Product name** দিন।\n\n"
                .'Merchant: **'.($draft['merchant'] ?? '—')."**\n\n"
                .'উদাহরণ: `name: Smart Watch`';
        }

        if (empty($draft['category_id'])) {
            return "👉 **Category** দিন।\n\n"
                .'Product: **'.($draft['name'] ?? '—')."**\n\n"
                .'উদাহরণ: `category: Electronics`';
        }

        if (empty($draft['variants'])) {
            return "👉 **Price ও stock** দিন।\n\n"
                .'Product: **'.($draft['name'] ?? '—')."**\n\n"
                ."উদাহরণ:\n"
                ."• `price: 2500, stock: 50`\n"
                .'• `price: 2500, stock: 50, compare_price: 3000`';
        }

        return match ($step) {
            'pc_attributes' => "👉 **Attributes** (ঐচ্ছিক)\n\n"
                ."উদাহরণ:\n"
                ."• `attributes: Color: Black, Material: Silicone`\n"
                ."• `attribute Size: M`\n\n"
                .'বাদ দিতে `skip` লিখুন।',
            'pc_extras' => "👉 **Images ও SEO** (ঐচ্ছিক)\n\n"
                ."উদাহরণ:\n"
                ."• `description: Premium smart watch`\n"
                ."• `image: https://example.com/watch.jpg`\n"
                ."• `tags: watch, smart, electronics`\n"
                ."• `featured: yes, publish: yes`\n\n"
                ."অথবা 📎 বাটন দিয়ে image upload করুন।\n"
                .'বাদ দিতে `skip` লিখুন।',
            default => '',
        };
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function confirmPrompt(ChatSession $session): array
    {
        return AgentResponseBuilder::make(
            "📋 **Product summary** — confirm করুন:\n\n"
            .$this->formatDraftSummary($session->draft_product ?? [])
            ."\n\nProduct তৈরি করতে **`yes`** লিখুন। বাতিল করতে `no`।",
            [
                'type' => 'product_create',
                'step' => 'pc_confirm',
                'draft_product' => $session->draft_product,
                'follow_ups' => ['yes', 'no'],
            ],
        );
    }

    public function resetProductCreate(ChatSession $session): void
    {
        if ($this->isProductCreateStep($session->step)) {
            $session->update([
                'step' => 'idle',
                'draft_product' => null,
            ]);
        }
    }

    private function resetSessionAfterCreate(ChatSession $session, int $productId): void
    {
        $payload = [
            'step' => 'idle',
            'draft_product' => null,
        ];

        if (Schema::hasColumn('chat_sessions', 'last_product_id')) {
            $payload['last_product_id'] = $productId;
        }

        try {
            $session->update($payload);
        } catch (\Throwable $e) {
            report($e);
            $session->update([
                'step' => 'idle',
                'draft_product' => null,
            ]);
        }
    }

    private function rememberLastProduct(ChatSession $session, int $productId): void
    {
        if (! Schema::hasColumn('chat_sessions', 'last_product_id')) {
            return;
        }

        try {
            $session->update(['last_product_id' => $productId]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeDraft(array $draft, array $parsed): array
    {
        foreach ($parsed as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($key === 'variants' && ! empty($value)) {
                $draft['variants'] = $value;
            } elseif ($key === 'attributes' && ! empty($value)) {
                $existing = $draft['attributes'] ?? [];
                $draft['attributes'] = array_values(array_merge($existing, $value));
            } elseif ($key === 'image_urls' && ! empty($value)) {
                $existing = $draft['image_urls'] ?? [];
                $draft['image_urls'] = array_values(array_unique(array_merge($existing, (array) $value)));
            } elseif ($key === 'pending_image_paths' && ! empty($value)) {
                $existing = $draft['pending_image_paths'] ?? [];
                $draft['pending_image_paths'] = array_values(array_unique(array_merge($existing, (array) $value)));
            } elseif (! isset($draft[$key]) || $draft[$key] === '' || $draft[$key] === null) {
                $draft[$key] = $value;
            }
        }

        if (empty($draft['sku']) && ! empty($draft['name'])) {
            $draft['sku'] = $this->generateSku((string) $draft['name']);
        }

        if (empty($draft['variants']) && isset($draft['price'])) {
            $draft['variants'] = [[
                'name' => 'Default',
                'sku' => $draft['sku'] ?? '',
                'price' => $draft['price'],
                'compare_price' => $draft['compare_price'] ?? null,
                'stock' => $draft['stock'] ?? 0,
                'weight' => $draft['weight'] ?? null,
            ]];
        }

        if (! empty($draft['variants']) && ! empty($draft['sku'])) {
            $draft['variants'] = $this->ensureVariantSkus($draft['variants'], (string) $draft['sku']);
        }

        if (isset($draft['action'])) {
            $draft['publish'] = in_array(strtolower((string) $draft['action']), ['publish', 'active'], true);
        }

        return $draft;
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<int, array<string, mixed>>
     */
    private function ensureVariantSkus(array $variants, string $productSku): array
    {
        $productSku = trim($productSku);
        $multiple = count($variants) > 1;

        foreach ($variants as $index => $variant) {
            $sku = trim((string) ($variant['sku'] ?? ''));
            if ($sku === '') {
                $sku = $multiple ? $productSku.'-V'.($index + 1) : $productSku;
            }
            $variants[$index]['sku'] = $sku;
        }

        return $variants;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function resolveReferences(array $draft): array
    {
        if (empty($draft['merchant_id']) && ! empty($draft['merchant'])) {
            $merchant = $this->resolveMerchant((string) $draft['merchant']);
            if ($merchant) {
                $draft['merchant_id'] = $merchant->id;
                $draft['merchant'] = $merchant->shop_name;
            }
        }

        if (empty($draft['category_id']) && ! empty($draft['category'])) {
            $category = $this->resolveCategory((string) $draft['category']);
            if ($category) {
                $draft['category_id'] = $category->id;
                $draft['category'] = $category->name;
            }
        }

        if (empty($draft['brand_id']) && ! empty($draft['brand'])) {
            $brand = $this->resolveBrand((string) $draft['brand']);
            if ($brand) {
                $draft['brand_id'] = $brand->id;
                $draft['brand'] = $brand->name;
            }
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function hasRequiredFields(array $draft): bool
    {
        return ! empty($draft['merchant_id'])
            && ! empty($draft['name'])
            && ! empty($draft['category_id'])
            && ! empty($draft['sku'])
            && ! empty($draft['variants']);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function nextMissingStep(array $draft, ?string $current = null): string
    {
        if (empty($draft['merchant_id'])) {
            return 'pc_merchant';
        }
        if (empty($draft['name']) || empty($draft['category_id']) || empty($draft['sku'])) {
            return 'pc_basic';
        }
        if (empty($draft['variants'])) {
            return 'pc_pricing';
        }

        if ($this->hasRequiredFields($draft)) {
            return match ($current) {
                'pc_attributes' => 'pc_extras',
                'pc_extras' => 'pc_confirm',
                default => 'pc_confirm',
            };
        }

        return 'pc_confirm';
    }

    private function stepAfterSkip(string $step): string
    {
        return match ($step) {
            'pc_attributes', 'pc_extras' => 'pc_confirm',
            default => 'pc_confirm',
        };
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<int, string>
     */
    private function validateDraft(array $draft): array
    {
        $validator = Validator::make($draft, [
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string'],
            'approval_status' => ['nullable', 'in:pending,approved,rejected'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'attributes' => ['nullable', 'array'],
            'image_urls' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return $validator->errors()->all();
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseWithLlm(string $message): array
    {
        try {
            $json = $this->llm->chat(
                config('market.model_google_search'),
                'Extract product creation fields from user text. Return JSON only with keys: merchant, name, category, brand, sku, price, compare_price, stock, weight, short_description, description, meta_title, meta_description, tags, approval_status, is_featured, publish, variants (array of {name, sku, price, compare_price, stock, weight}), attributes (array of {name, value}), image_urls (array of URLs). Omit unknown keys.',
                $message,
                true,
                0.1,
            );

            $decoded = json_decode($json, true);

            return is_array($decoded) ? $this->parser->normalizePayload($decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function listMerchants(): string
    {
        $merchants = Merchant::query()
            ->where('status', 'active')
            ->orderBy('shop_name')
            ->limit(12)
            ->get(['id', 'shop_name']);

        if ($merchants->isEmpty()) {
            return '⚠️ কোনো active merchant নেই। আগে Admin → Merchants থেকে merchant যোগ করুন।';
        }

        return $merchants
            ->map(fn (Merchant $m) => "• **{$m->shop_name}** (ID: {$m->id})")
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function formatDraftSummary(array $draft): string
    {
        $lines = [];
        $lines[] = '• Merchant: **'.($draft['merchant'] ?? ($draft['merchant_id'] ?? '—')).'**';
        $lines[] = '• Name: **'.($draft['name'] ?? '—').'**';
        $lines[] = '• Category: **'.($draft['category'] ?? ($draft['category_id'] ?? '—')).'**';
        $lines[] = '• SKU: `'.($draft['sku'] ?? '—').'`';

        if (! empty($draft['brand'])) {
            $lines[] = '• Brand: **'.$draft['brand'].'**';
        }

        if (! empty($draft['variants'])) {
            $variantLines = collect($draft['variants'])->map(function (array $v) {
                $price = isset($v['price']) ? '৳'.number_format((float) $v['price']) : '—';
                $stock = $v['stock'] ?? 0;

                return '  - '.($v['name'] ?? 'Default').": {$price}, stock {$stock}";
            })->implode("\n");
            $lines[] = "• Variants:\n{$variantLines}";
        }

        if (! empty($draft['attributes'])) {
            $attrLines = collect($draft['attributes'])->map(
                fn (array $a) => '  - '.($a['name'] ?? '').': '.($a['value'] ?? ''),
            )->implode("\n");
            $lines[] = "• Attributes:\n{$attrLines}";
        }

        if (! empty($draft['short_description'])) {
            $lines[] = '• Short description: '.$draft['short_description'];
        }
        if (! empty($draft['description'])) {
            $lines[] = '• Description: '.$draft['description'];
        }
        if (! empty($draft['image_urls'])) {
            $lines[] = '• Images: '.count((array) $draft['image_urls']).' URL(s)';
        }
        if (! empty($draft['pending_image_paths'])) {
            $lines[] = '• Uploaded images: '.count((array) $draft['pending_image_paths']);
        }
        if (! empty($draft['tags'])) {
            $lines[] = '• Tags: '.$draft['tags'];
        }

        $lines[] = '• Featured: '.(! empty($draft['is_featured']) ? 'Yes' : 'No');
        $lines[] = '• Publish: '.(! isset($draft['publish']) || $draft['publish'] ? 'Yes' : 'Draft');

        return implode("\n", $lines);
    }

    private function resolveMerchant(string $needle): ?Merchant
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        if (preg_match('/\b(?:merchant|shop|store|দোকান)\s*[:=]\s*(.+)$/iu', $needle, $m)) {
            $needle = trim($m[1]);
        }

        if ($needle === '') {
            return null;
        }

        if (ctype_digit($needle)) {
            return Merchant::query()->where('status', 'active')->find((int) $needle);
        }

        $exact = Merchant::query()
            ->where('status', 'active')
            ->where(function ($q) use ($needle) {
                $q->where('shop_name', $needle)
                    ->orWhere('shop_name', 'like', "%{$needle}%");
            })
            ->orderByRaw('CASE WHEN shop_name = ? THEN 0 ELSE 1 END', [$needle])
            ->first();

        if ($exact) {
            return $exact;
        }

        return $this->fuzzyMatchMerchant($needle);
    }

    private function fuzzyMatchMerchant(string $needle): ?Merchant
    {
        $words = $this->significantNameWords($needle);
        if ($words === []) {
            return null;
        }

        $merchants = Merchant::query()
            ->where('status', 'active')
            ->orderBy('shop_name')
            ->get(['id', 'shop_name']);

        if ($merchants->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($merchants as $merchant) {
            $score = $this->merchantMatchScore($words, $merchant->shop_name);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $merchant;
            }
        }

        if ($best === null || $bestScore < 0.45) {
            return null;
        }

        $runnerUp = 0.0;
        foreach ($merchants as $merchant) {
            if ($merchant->id === $best->id) {
                continue;
            }
            $runnerUp = max($runnerUp, $this->merchantMatchScore($words, $merchant->shop_name));
        }

        if ($runnerUp > 0 && ($bestScore - $runnerUp) < 0.15) {
            return null;
        }

        return $best;
    }

    /**
     * @return array<int, string>
     */
    private function significantNameWords(string $text): array
    {
        $lower = strtolower($text);
        $lower = preg_replace('/[^a-z0-9\s]/u', ' ', $lower) ?? $lower;
        $stop = ['store', 'shop', 'the', 'and', 'for', 'ltd', 'bd', 'বিডি'];

        return array_values(array_unique(array_filter(
            preg_split('/\s+/', $lower) ?: [],
            fn (string $w) => strlen($w) >= 3 && ! in_array($w, $stop, true),
        )));
    }

    /**
     * @param  array<int, string>  $needleWords
     */
    private function merchantMatchScore(array $needleWords, string $shopName): float
    {
        $shopLower = strtolower($shopName);
        $matched = 0;

        foreach ($needleWords as $word) {
            if (str_contains($shopLower, $word)) {
                $matched++;
            }
        }

        if ($matched === 0) {
            return 0.0;
        }

        return $matched / max(1, count($needleWords));
    }

    private function resolveCategory(string $needle): ?Category
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        if (ctype_digit($needle)) {
            return Category::query()->active()->find((int) $needle);
        }

        return Category::query()
            ->active()
            ->where(function ($q) use ($needle) {
                $q->where('name', $needle)
                    ->orWhere('name', 'like', "%{$needle}%");
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$needle])
            ->first();
    }

    private function resolveBrand(string $needle): ?Brand
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        if (ctype_digit($needle)) {
            return Brand::query()->active()->find((int) $needle);
        }

        return Brand::query()
            ->active()
            ->where(function ($q) use ($needle) {
                $q->where('name', $needle)
                    ->orWhere('name', 'like', "%{$needle}%");
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$needle])
            ->first();
    }

    private function generateSku(string $name): string
    {
        $base = Str::upper(Str::slug(Str::limit($name, 20, ''), '-'));
        $base = $base !== '' ? $base : 'PRODUCT';

        do {
            $sku = $base.'-'.Str::upper(Str::random(4));
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function isSkipMessage(string $message): bool
    {
        return $this->matchesKeyword(strtolower(trim($message)), self::SKIP_KW);
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
            if (mb_strlen($k) > 2 && str_contains($trimmed, $k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function followUpsForStep(string $step): array
    {
        return match ($step) {
            'pc_merchant' => ['merchant: '.$this->sampleMerchantLabel()],
            'pc_basic' => ['name: Smart Watch', 'category: Electronics'],
            'pc_pricing' => ['price: 2500, stock: 50', 'variant: Large, price: 2800, stock: 20'],
            'pc_attributes' => ['attributes: Color: Black, Size: M', 'skip'],
            'pc_extras' => ['image: https://example.com/product.jpg, tags: watch', 'skip'],
            default => self::exampleFollowUps(),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function exampleFollowUps(): array
    {
        $merchant = Merchant::query()
            ->where('status', 'active')
            ->orderBy('shop_name')
            ->value('shop_name') ?: 'TechZone BD';

        return [
            "create product: merchant: {$merchant}, name: Smart Watch, category: Electronics, price: 2500, stock: 50",
            'create product',
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedImages
     * @return array{content: string, meta: array<string, mixed>}|null
     */
    private function handleUploadedImages(ChatSession $session, string $message, array $uploadedImages, ?int $productId): ?array
    {
        $targetProductId = $productId ?? $session->last_product_id;

        if ($targetProductId && ! $this->isProductCreateStep($session->step)) {
            return $this->attachImagesToExistingProduct($session, (int) $targetProductId, $uploadedImages);
        }

        if (! $this->isProductCreateStep($session->step) && ! $this->isCreateIntent($message)) {
            return AgentResponseBuilder::make(
                "📷 Image পেয়েছি, কিন্তু কোনো product create flow চলছে না।\n\n"
                .'নতুন product তৈরি করতে `create product` লিখুন, অথবা আগে তৈরি করা product-এ image দিতে product create করুন।',
                ['type' => 'product_create', 'follow_ups' => self::exampleFollowUps()],
            );
        }

        $draft = $session->draft_product ?? [];
        $paths = $this->storeUploadedImages($uploadedImages);

        if ($paths === []) {
            return AgentResponseBuilder::make(
                '❌ Image upload ব্যর্থ হয়েছে। JPEG, PNG, JPG বা WebP (max 5MB) আবার চেষ্টা করুন।',
                [
                    'type' => 'product_create',
                    'step' => $session->step,
                    'draft_product' => $draft,
                    'follow_ups' => $this->followUpsForStep($session->step ?: 'pc_extras'),
                ],
            );
        }

        $draft['pending_image_paths'] = array_values(array_unique(array_merge($draft['pending_image_paths'] ?? [], $paths)));

        if ($message !== '') {
            $parsed = $this->parser->parse($message);
            if (app(\App\Services\Market\Llm\LlmProviderManager::class)->isReady() && count($parsed) < 2) {
                $parsed = array_merge($parsed, $this->parseWithLlm($message));
            }
            $draft = $this->mergeDraft($draft, $parsed);
            $draft = $this->resolveReferences($draft);
        }

        $nextStep = $session->step === 'idle'
            ? $this->nextMissingStep($draft)
            : ($this->nextMissingStep($draft, $session->step) === 'pc_confirm' && $session->step === 'pc_extras'
                ? 'pc_confirm'
                : $this->nextMissingStep($draft, $session->step));

        if ($session->step === 'idle' && empty($draft['merchant_id'])) {
            $nextStep = 'pc_merchant';
        }

        $session->update([
            'draft_product' => $draft,
            'step' => $nextStep,
        ]);

        $session = $session->fresh();
        $count = count($paths);

        if ($session->step === 'pc_confirm') {
            return AgentResponseBuilder::make(
                "✅ {$count}টি image upload হয়েছে।\n\n".$this->formatDraftSummary($draft)
                ."\n\nProduct তৈরি করতে `yes` লিখুন।",
                [
                    'type' => 'product_create',
                    'step' => 'pc_confirm',
                    'draft_product' => $draft,
                    'follow_ups' => ['yes', 'no'],
                ],
            );
        }

        $stepResponse = $this->promptForStep($session);
        $stepResponse['content'] = "✅ {$count}টি image সংরক্ষণ হয়েছে।\n\n".$stepResponse['content'];

        return $stepResponse;
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedImages
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function attachImagesToExistingProduct(ChatSession $session, int $productId, array $uploadedImages): array
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            return AgentResponseBuilder::make(
                '❌ Product খুঁজে পাওয়া যায়নি। `create product` দিয়ে নতুন product তৈরি করুন।',
                ['type' => 'product_create', 'follow_ups' => self::exampleFollowUps()],
            );
        }

        $product = $this->productService->addImages($product, $uploadedImages);
        $this->rememberLastProduct($session, $product->id);

        $editUrl = route('admin.products.edit', $product);
        $storeUrl = route('products.show', $product->slug);
        $imageCount = count($uploadedImages);

        return AgentResponseBuilder::make(
            "✅ **{$product->name}**-এ {$imageCount}টি image যোগ হয়েছে!\n\n"
            ."- মোট images: {$product->images()->count()}\n"
            ."- Primary image আপডেট হয়েছে\n\n"
            ."[Storefront-এ দেখুন →]({$storeUrl})",
            [
                'type' => 'product_image_updated',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->primary_image_url,
                    'store_url' => $storeUrl,
                    'admin_url' => $editUrl,
                ],
                'follow_ups' => ['create product', 'watch', 'trending product ki?'],
            ],
        );
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedImages
     * @return array<int, string>
     */
    private function storeUploadedImages(array $uploadedImages): array
    {
        $paths = [];

        foreach ($uploadedImages as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $paths[] = $file->store('products', 'public');
        }

        return $paths;
    }
}
