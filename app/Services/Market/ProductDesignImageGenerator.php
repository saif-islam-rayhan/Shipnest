<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductDesignImageGenerator
{
    public function __construct(
        private readonly LlmClient $llm,
    ) {}

    /**
     * Generate a product design image from a free text prompt (Pollinations — no paid key required).
     *
     * @return array{description: string, image_url: string, image_path: string, prompt: string}
     */
    public function generate(string $userPrompt): array
    {
        $userPrompt = trim($userPrompt);
        if ($userPrompt === '') {
            throw new RuntimeException('Please describe what you want to design.');
        }

        $enhancedPrompt = $this->buildImagePrompt($userPrompt);
        $seed = random_int(1, 2_147_483_647);
        $remoteUrl = $this->buildRemoteUrl($enhancedPrompt, $seed);

        $imagePath = $this->downloadAndStore($remoteUrl, $userPrompt);
        $imageUrl = asset('storage/'.$imagePath);

        return [
            'description' => $this->buildDescription($userPrompt),
            'image_url' => $imageUrl,
            'image_path' => $imagePath,
            'prompt' => $enhancedPrompt,
        ];
    }

    private function buildImagePrompt(string $userPrompt): string
    {
        return implode(', ', [
            'Professional ecommerce product photography',
            $userPrompt,
            'clean studio lighting',
            'centered product on soft neutral background',
            'high detail',
            'commercial catalog style',
            'no text overlay',
            'no watermark',
        ]);
    }

    private function buildRemoteUrl(string $prompt, int $seed): string
    {
        $encoded = rawurlencode($prompt);
        $base = rtrim((string) config('services.pollinations.image_base', 'https://image.pollinations.ai/prompt'), '/');
        $query = http_build_query(array_filter([
            'width' => 1024,
            'height' => 1024,
            'seed' => $seed,
            'nologo' => 'true',
            'enhance' => 'true',
            'model' => config('services.pollinations.model', 'flux'),
            'key' => config('services.pollinations.key') ?: null,
        ]));

        // Legacy path style: /prompt/{prompt}?params
        if (str_contains($base, 'image.pollinations.ai')) {
            return $base.'/'.$encoded.'?'.$query;
        }

        // gen.pollinations.ai style: /image/{prompt}?params
        return $base.'/'.$encoded.'?'.$query;
    }

    private function downloadAndStore(string $remoteUrl, string $label): string
    {
        $response = Http::timeout(120)
            ->withHeaders([
                'User-Agent' => 'ShipNest/1.0 (+product-design)',
                'Accept' => 'image/*,*/*',
            ])
            ->get($remoteUrl);

        if (! $response->successful()) {
            Log::warning('Pollinations image request failed', [
                'status' => $response->status(),
                'url' => Str::limit($remoteUrl, 200),
            ]);
            throw new RuntimeException('Image generation failed. Please try again in a moment.');
        }

        $body = $response->body();
        if (strlen($body) < 2048) {
            throw new RuntimeException('Image generation returned an empty result. Please try a clearer prompt.');
        }

        $mime = strtolower((string) $response->header('Content-Type'));
        $ext = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };

        $filename = 'ai-designs/'.now()->format('Y/m/d').'/'.Str::slug(Str::limit($label, 40, '')).'-'.Str::random(8).'.'.$ext;
        Storage::disk('public')->put($filename, $body);

        return $filename;
    }

    private function buildDescription(string $userPrompt): string
    {
        $fallback = 'I designed a product concept for “'.$userPrompt.'” — ecommerce-ready studio shot with clean lighting. You can use this visual on a new product listing or refine with a follow-up prompt.';

        try {
            $text = trim($this->llm->chat(
                '',
                'You write short product-design captions for an ecommerce seller tool. Reply in 1-2 sentences, friendly and concrete. No markdown headings. Mention the visual concept only.',
                'User asked to design: '.$userPrompt,
                false,
                0.6,
            ));

            return $text !== '' ? $text : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
