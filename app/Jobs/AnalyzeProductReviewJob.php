<?php

namespace App\Jobs;

use App\Models\ProductReview;
use App\Services\Admin\ReviewAgentIntakeService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class AnalyzeProductReviewJob
{
    use Dispatchable;

    public function __construct(
        public readonly int $reviewId,
    ) {}

    public function handle(ReviewAgentIntakeService $intake): void
    {
        $review = ProductReview::query()->find($this->reviewId);
        if (! $review || $review->status !== 'pending') {
            return;
        }

        if ($review->agent_analyzed_at !== null && $review->sentiment) {
            return;
        }

        try {
            $intake->process($review);
        } catch (\Throwable $e) {
            Log::error('AnalyzeProductReviewJob failed', [
                'review_id' => $this->reviewId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
