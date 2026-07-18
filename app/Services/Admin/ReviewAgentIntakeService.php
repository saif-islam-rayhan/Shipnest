<?php

namespace App\Services\Admin;

use App\Models\Notification;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Market\ReviewSentimentAnalyzer;

class ReviewAgentIntakeService
{
    public function __construct(
        private readonly ReviewSentimentAnalyzer $analyzer,
    ) {}

    public function process(ProductReview $review): ProductReview
    {
        $review = $this->analyzer->analyzeAndPersist($review);
        $this->notifyAdmins($review);

        return $review;
    }

    private function notifyAdmins(ProductReview $review): void
    {
        $sentiment = $review->sentiment ?? 'positive';
        $label = $sentiment === 'positive' ? 'Positive' : 'Negative';
        $summary = (string) ($review->agent_summary ?: "New review #{$review->id} — {$label}.");

        $admins = User::role(['super_admin', 'admin'])->get();

        foreach ($admins as $admin) {
            Notification::query()->create([
                'user_id' => $admin->id,
                'type' => 'review_agent',
                'title' => "{$label} review pending approval",
                'body' => strip_tags(str_replace('**', '', $summary)),
                'data' => [
                    'review_id' => $review->id,
                    'product_id' => $review->product_id,
                    'sentiment' => $sentiment,
                    'agent_summary' => $summary,
                ],
            ]);
        }
    }
}
