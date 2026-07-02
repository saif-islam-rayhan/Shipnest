<?php

namespace App\Services\Market;

use App\Models\AnalysisRecord;

class AnalysisPipelineService
{
    private const SITE_WEIGHTS = [
        'daraz' => 0.16, 'bikroy' => 0.09, 'shajgoj' => 0.07, 'facebook' => 0.11,
        'pickaboo' => 0.11, 'othoba' => 0.11, 'ajkerdeal' => 0.09, 'alibaba' => 0.26,
    ];

    public function __construct(private LlmClient $llm) {}

    public function run(string $query, ?int $month = null, array $sites = []): AnalysisRecord
    {
        $sites = $sites ?: config('market.sites');
        $runMode = config('market.run_mode');
        $verdicts = [];

        foreach ($sites as $site) {
            $data = $this->loadSiteData($site, $query, $runMode);
            $verdicts[] = $this->computeSiteVerdict($site, $data);
        }

        $active = array_filter($verdicts, fn ($v) => ($v['label'] ?? '') !== 'SKIPPED');
        $goodCount = count(array_filter($active, fn ($v) => $v['label'] === 'GOOD'));
        $liveCount = count($active);

        if ($liveCount < 2) {
            $verdict = 'INSUFFICIENT_DATA';
            $confidence = 0.0;
            $summary = "Only {$liveCount} site(s) returned data.";
        } elseif ($goodCount >= min(5, max(2, $liveCount - 1))) {
            $verdict = 'CONFIRMED SELLING';
            $confidence = $this->weightedConfidence($active);
            $summary = $this->llmSummary($active, $verdict, $confidence);
        } elseif ($goodCount >= (int) ceil($liveCount / 2)) {
            $verdict = 'POTENTIALLY SELLING';
            $confidence = $this->weightedConfidence($active);
            $summary = $this->llmSummary($active, $verdict, $confidence);
        } else {
            $verdict = 'NOT RECOMMENDED';
            $confidence = $this->weightedConfidence($active);
            $summary = $this->llmSummary($active, $verdict, $confidence);
        }

        return AnalysisRecord::create([
            'query' => $query,
            'target_month' => $month,
            'selected_sites' => $sites,
            'verdict' => $verdict,
            'confidence' => $confidence,
            'summary' => $summary,
            'site_verdicts' => $verdicts,
            'raw_payload' => ['verdicts' => $verdicts],
            'run_mode' => $runMode,
        ]);
    }

    private function loadSiteData(string $site, string $query, string $runMode): array
    {
        $path = config('market.mock_data_path')."/{$site}.json";
        if ($runMode === 'demo' && file_exists($path)) {
            return json_decode(file_get_contents($path), true) ?? [];
        }

        return ['items' => [], 'query' => $query, 'site' => $site];
    }

    private function computeSiteVerdict(string $site, array $data): array
    {
        $items = $data['items'] ?? $data['products'] ?? [];
        if (empty($items)) {
            return ['site' => $site, 'label' => 'SKIPPED', 'score' => 0, 'reason' => 'No data'];
        }
        $count = count($items);
        $score = min(95, 40 + $count * 8);
        $label = $score >= 70 ? 'GOOD' : ($score >= 50 ? 'MODERATE' : 'WEAK');

        return [
            'site' => $site,
            'label' => $label,
            'score' => $score,
            'item_count' => $count,
            'reason' => "{$count} listings found",
        ];
    }

    private function weightedConfidence(array $verdicts): float
    {
        $sum = 0;
        $weightSum = 0;
        foreach ($verdicts as $v) {
            $w = self::SITE_WEIGHTS[$v['site']] ?? 0.1;
            $sum += ($v['score'] ?? 0) * $w;
            $weightSum += $w;
        }

        return $weightSum > 0 ? round($sum / $weightSum, 1) : 0.0;
    }

    private function llmSummary(array $verdicts, string $verdict, float $confidence): string
    {
        if (! config('market.use_live_llm')) {
            return "Verdict: {$verdict} ({$confidence}% confidence). ".count($verdicts).' sites analyzed.';
        }
        try {
            $prompt = 'Write 2-3 sentence business summary for Bangladesh e-commerce in Bangla mixed with English.';
            $user = 'Verdicts: '.json_encode($verdicts)."\nFinal: {$verdict} ({$confidence}%)";

            return $this->llm->chat(config('market.model_google_search'), $prompt, $user, temperature: 0.3);
        } catch (\Throwable) {
            return "Verdict: {$verdict} ({$confidence}% confidence).";
        }
    }
}
