<?php

namespace App\Observers;

use App\Jobs\AnalyzeProductReviewJob;
use App\Models\ProductReview;

class ProductReviewObserver
{
    public function created(ProductReview $review): void
    {
        if ($review->status !== 'pending') {
            return;
        }

        if ($review->agent_analyzed_at !== null) {
            return;
        }

        // Runs after the HTTP response so submit stays fast; no queue worker required.
        AnalyzeProductReviewJob::dispatchAfterResponse($review->id);
    }
}
