<?php

namespace App\Services\Market;

class GooglePeriod
{
    public function __construct(
        public int $monthFrom,
        public int $monthTo,
        public int $yearFrom,
        public int $yearTo,
    ) {}

    public function label(): string
    {
        $months = config('market.month_names');
        if ($this->monthFrom === $this->monthTo && $this->yearFrom === $this->yearTo) {
            return $months[$this->monthFrom].' '.$this->yearFrom;
        }

        return $months[$this->monthFrom].' – '.$months[$this->monthTo].' '.$this->yearFrom;
    }

    public function labelBn(): string
    {
        $months = config('market.month_names_bn');
        if ($this->monthFrom === $this->monthTo && $this->yearFrom === $this->yearTo) {
            return $months[$this->monthFrom].' '.$this->yearFrom;
        }

        return $months[$this->monthFrom].' – '.$months[$this->monthTo].' '.$this->yearFrom;
    }

    public function toArray(): array
    {
        return [
            'month_from' => $this->monthFrom,
            'month_to' => $this->monthTo,
            'year_from' => $this->yearFrom,
            'year_to' => $this->yearTo,
            'label' => $this->label(),
            'label_bn' => $this->labelBn(),
        ];
    }
}

class GooglePeriodParser
{
    private array $monthMap;

    public function __construct()
    {
        $this->monthMap = array_merge(
            collect(config('market.month_names'))->mapWithKeys(fn ($n, $k) => [strtolower($n) => $k])->all(),
            collect(config('market.month_names_bn'))->mapWithKeys(fn ($n, $k) => [$n => $k])->all(),
            ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
                'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12]
        );
    }

    public function parse(string $text): ?GooglePeriod
    {
        $lower = strtolower($text);
        $year = null;
        if (preg_match('/\b(20\d{2})\b/', $text, $ym)) {
            $year = (int) $ym[1];
        }
        $year ??= (int) date('Y');

        foreach ($this->monthMap as $token => $num) {
            if (preg_match('/\b'.preg_quote($token, '/').'\b/ui', $lower)) {
                return new GooglePeriod($num, $num, $year, $year);
            }
        }

        if (preg_match('/\blast\s+month\b/i', $lower)) {
            $d = now()->subMonth();

            return new GooglePeriod($d->month, $d->month, $d->year, $d->year);
        }

        if (preg_match('/\bthis\s+month\b/i', $lower) || str_contains($text, 'এই মাস')) {
            return new GooglePeriod((int) date('n'), (int) date('n'), (int) date('Y'), (int) date('Y'));
        }

        return null;
    }

    public static function periodPrompt(): string
    {
        return "কোন মাস/সময়ের trending জানতে চান?\nউদাহরণ: `June 2026`, `last month`, `March-May 2026`";
    }
}
