<?php

namespace App\Services\Market;

use App\Models\ChatSession;
use App\Models\ProductReview;

class AdminReviewModerationAgent
{
    private const REVIEW_KW = [
        'pending reviews', 'pending review', 'review check', 'check reviews',
        'approve reviews', 'review moderation', 'moderate reviews', 'reviews pending',
        'review approve', 'new reviews', 'new review', 'review notification',
        'review notifications', 'ki review', 'review asche', 'review esece',
        'রিভিউ চেক', 'পেন্ডিং রিভিউ', 'রিভিউ দেখো', 'রিভিউ মডারেশন', 'নতুন রিভিউ',
    ];

    private const LIST_ONLY_KW = [
        'new reviews', 'new review', 'review notification', 'review notifications',
        'ki review', 'review asche', 'review esece', 'নতুন রিভিউ',
        'review summary', 'review status',
    ];

    private const APPROVE_KW = [
        'approve', 'approved', 'yes approve', 'approve koro', 'approve করো',
        'অ্যাপ্রুভ', 'অনুমোদন',
    ];

    private const REJECT_KW = [
        'reject', 'rejected', 'yes reject', 'reject koro', 'reject করো',
        'রিজেক্ট',
    ];

    public function __construct(
        private readonly ReviewSentimentAnalyzer $analyzer,
    ) {}

    public function isReviewIntent(string $message): bool
    {
        $lower = strtolower(trim($message));

        foreach (self::REVIEW_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/\b(pending|check|moderate|approve|reject|new|summary)\b.*\b(review|reviews|রিভিউ)\b/ui',
            $message,
        ) || (bool) preg_match(
            '/\b(review|reviews|রিভিউ)\b.*\b(pending|check|moderate|approve|new|notification|summary|asche|esece)\b/ui',
            $message,
        );
    }

    public function isReviewStep(string $step): bool
    {
        return $step === 'rv_decide';
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    public function handle(ChatSession $session, string $message): array
    {
        $msg = trim($message);
        $lower = strtolower($msg);

        if ($this->isReviewStep($session->step)) {
            if ($this->matchesKeyword($lower, self::APPROVE_KW)) {
                return $this->applyDecision($session, 'approved');
            }

            if ($this->matchesKeyword($lower, self::REJECT_KW)) {
                return $this->applyDecision($session, 'rejected');
            }

            return AgentResponseBuilder::make(
                "⚠️ **Approve** বা **Reject** বাটনে ক্লিক করুন।\n\n"
                .'অন্য কিছু করতে চাইলে `cancel` লিখুন।',
                [
                    'type' => 'review_moderation',
                    'step' => 'rv_decide',
                    'follow_ups' => ['Approve', 'Reject'],
                ],
            );
        }

        if ($this->isListOnlyIntent($lower)) {
            return $this->summarizePending($session);
        }

        if ($this->isReviewIntent($msg)) {
            return $this->showNextPending($session);
        }

        return AgentResponseBuilder::make(
            'Pending review দেখতে `pending reviews` লিখুন। সারাংশের জন্য `new reviews`।',
            [
                'type' => 'review_moderation',
                'follow_ups' => self::exampleFollowUps(),
            ],
        );
    }

    public function reset(ChatSession $session): void
    {
        if ($this->isReviewStep($session->step)) {
            $session->update([
                'step' => 'idle',
                'draft_product' => null,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function exampleFollowUps(): array
    {
        return ['pending reviews', 'new reviews'];
    }

    private function isListOnlyIntent(string $lower): bool
    {
        foreach (self::LIST_ONLY_KW as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function summarizePending(ChatSession $session): array
    {
        $session->update([
            'step' => 'idle',
            'draft_product' => null,
        ]);

        $reviews = ProductReview::query()
            ->pending()
            ->with(['product', 'user'])
            ->latest()
            ->limit(10)
            ->get();

        if ($reviews->isEmpty()) {
            return AgentResponseBuilder::make(
                '✅ কোনো **pending review** নেই।',
                [
                    'type' => 'review_moderation',
                    'follow_ups' => ['create product', 'trending product ki?'],
                ],
            );
        }

        $positive = $reviews->where('sentiment', 'positive')->count();
        $negative = $reviews->where('sentiment', 'negative')->count();
        $unknown = $reviews->count() - $positive - $negative;

        $lines = [
            '📬 **Agent review inbox** — '.$reviews->count().' pending (showing latest)',
            '',
            "😊 Positive: **{$positive}** · 😕 Negative: **{$negative}**"
                .($unknown > 0 ? " · ❔ Unsorted: **{$unknown}**" : ''),
            '',
        ];

        foreach ($reviews as $review) {
            $this->ensureAnalyzed($review);
            $sentiment = $review->sentiment ?? 'positive';
            $label = $sentiment === 'positive' ? 'Positive' : 'Negative';
            $emoji = $sentiment === 'positive' ? '😊' : '😕';
            $product = $review->product->name ?? 'Product';
            $summary = $review->agent_summary
                ?: $this->analyzer->buildSummary($review, $sentiment);

            $lines[] = "{$emoji} **#{$review->id}** ({$label}) — {$product}";
            $lines[] = '> '.strip_tags(str_replace('**', '', $summary));
            $lines[] = '';
        }

        $lines[] = 'একটা একটা করে approve করতে `pending reviews` লিখুন।';

        return AgentResponseBuilder::make(implode("\n", $lines), [
            'type' => 'review_moderation',
            'follow_ups' => ['pending reviews'],
            'thought_process' => [
                'Listed pending reviews with saved agent sentiment',
            ],
        ]);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function showNextPending(ChatSession $session): array
    {
        $review = ProductReview::query()
            ->pending()
            ->with(['product', 'user'])
            ->oldest()
            ->first();

        if (! $review) {
            $session->update([
                'step' => 'idle',
                'draft_product' => null,
            ]);

            return AgentResponseBuilder::make(
                '✅ কোনো **pending review** নেই। সব clear!',
                [
                    'type' => 'review_moderation',
                    'follow_ups' => ['create product', 'trending product ki?'],
                ],
            );
        }

        $this->ensureAnalyzed($review);

        $remaining = ProductReview::query()->pending()->count();
        $sentiment = $review->sentiment ?? $this->analyzer->detect($review);
        $label = $sentiment === 'positive' ? 'Positive' : 'Negative';
        $emoji = $sentiment === 'positive' ? '😊' : '😕';

        $session->update([
            'step' => 'rv_decide',
            'draft_product' => [
                '_type' => 'review_moderation',
                'review_id' => $review->id,
                'sentiment' => $sentiment,
            ],
        ]);

        $productName = $review->product->name ?? 'Product';
        $customer = $review->user->name ?? 'Customer';
        $stars = str_repeat('★', max(0, min(5, (int) $review->rating)))
            .str_repeat('☆', max(0, 5 - (int) $review->rating));
        $title = trim((string) $review->title);
        $body = trim((string) $review->body);
        $bodyPreview = mb_strlen($body) > 400 ? mb_substr($body, 0, 400).'…' : $body;

        $content = "📝 **Review #{$review->id}** — pending ({$remaining} left)\n\n"
            ."**Product:** {$productName}\n"
            ."**Customer:** {$customer}\n"
            ."**Rating:** {$stars} ({$review->rating}/5)\n\n";

        if ($title !== '') {
            $content .= "**Title:** {$title}\n";
        }

        if ($bodyPreview !== '') {
            $content .= "**Review:**\n> {$bodyPreview}\n\n";
        } else {
            $content .= "\n";
        }

        if ($review->agent_summary) {
            $content .= '🤖 **Agent:** '.strip_tags(str_replace('**', '', $review->agent_summary))."\n\n";
        }

        $imageCount = count($review->images ?? []);
        if ($imageCount > 0) {
            $content .= "📷 Review-এ **{$imageCount}** image আছে — text + image দুটোই analyze করা হয়েছে।\n\n";
        }

        $content .= "{$emoji} এটা **{$label} review**।\n\n"
            .'**Approve** না **Reject** — কোনটা করবেন?';

        return AgentResponseBuilder::make($content, [
            'type' => 'review_moderation',
            'step' => 'rv_decide',
            'review_id' => $review->id,
            'sentiment' => $sentiment,
            'follow_ups' => ['Approve', 'Reject', 'new reviews'],
            'thought_process' => [
                'Loaded oldest pending product review',
                'Sentiment: '.$label.($imageCount > 0
                    ? ' (text + image analysis)'
                    : ' (text/rating analysis)'),
            ],
        ]);
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function applyDecision(ChatSession $session, string $status): array
    {
        $reviewId = (int) data_get($session->draft_product, 'review_id');
        $review = $reviewId > 0
            ? ProductReview::query()->with(['product', 'user'])->find($reviewId)
            : null;

        if (! $review || $review->status !== 'pending') {
            $session->update([
                'step' => 'idle',
                'draft_product' => null,
            ]);

            $next = $this->showNextPending($session);
            $next['content'] = "⚠️ এই review আর pending নেই। পরেরটা দেখাচ্ছি…\n\n".$next['content'];

            return $next;
        }

        $review->update(['status' => $status]);

        $actionLabel = $status === 'approved' ? 'Approved ✅' : 'Rejected ❌';
        $productName = $review->product->name ?? 'Product';

        $session->update([
            'step' => 'idle',
            'draft_product' => null,
        ]);

        $next = $this->showNextPending($session);
        $next['content'] = "**{$actionLabel}** — Review #{$review->id} ({$productName}).\n\n".$next['content'];
        $next['meta']['thought_process'] = array_merge(
            ["Admin clicked {$status} for review #{$review->id}"],
            $next['meta']['thought_process'] ?? [],
        );

        return $next;
    }

    private function ensureAnalyzed(ProductReview $review): void
    {
        if ($review->agent_analyzed_at && $review->sentiment) {
            return;
        }

        $this->analyzer->analyzeAndPersist($review);
        $review->refresh();
        $review->load(['product', 'user']);
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matchesKeyword(string $lower, array $keywords): bool
    {
        $normalized = trim($lower);

        foreach ($keywords as $kw) {
            if ($normalized === $kw || str_starts_with($normalized, $kw.' ')) {
                return true;
            }
        }

        return false;
    }
}
