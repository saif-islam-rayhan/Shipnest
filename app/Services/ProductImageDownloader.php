<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageDownloader
{
    private const MIN_BYTES = 2048;

    private const MIN_DIMENSION = 50;

    /**
     * Download remote image (or reuse existing local file) into storage/app/public/products.
     *
     * @param  string|null  $label  Product name used for text placeholder when download fails.
     */
    public function ensureLocal(string $source, string $basename, bool $force = false, ?string $label = null): string
    {
        $basename = Str::slug(pathinfo($basename, PATHINFO_FILENAME)) ?: 'product';

        if (! str_starts_with($source, 'http://') && ! str_starts_with($source, 'https://')) {
            if ($source !== '' && $this->isHealthyPath($source)) {
                return $source;
            }
        }

        if (! $force) {
            $existing = $this->findValidLocal($basename);
            if ($existing) {
                return $existing;
            }
        }

        $this->deleteLocalVariants($basename);

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            $downloaded = $this->download($source, $basename);
            if ($downloaded) {
                return $downloaded;
            }
        }

        $placeholder = $this->downloadPlaceholder($basename, $label);
        if ($this->isHealthyPath($placeholder)) {
            return $placeholder;
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            return $source;
        }

        return $placeholder;
    }

    public function isHealthyPath(string $relativePath): bool
    {
        if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return true;
        }

        if (! Storage::disk('public')->exists($relativePath)) {
            return false;
        }

        return $this->isHealthyFile(Storage::disk('public')->path($relativePath));
    }

    private function findValidLocal(string $basename): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = 'products/'.$basename.'.'.$ext;
            if ($this->isHealthyPath($path)) {
                return $path;
            }
        }

        return null;
    }

    private function deleteLocalVariants(string $basename): void
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = 'products/'.$basename.'.'.$ext;
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function download(string $url, string $basename): ?string
    {
        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'User-Agent' => 'ShipNest-Seeder/1.0',
                    'Accept' => 'image/*',
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            if (! $this->isHealthyBytes($body)) {
                return null;
            }

            $ext = $this->extensionFrom($url, $response->header('Content-Type'));
            $path = 'products/'.$basename.'.'.$ext;

            Storage::disk('public')->put($path, $body);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private function downloadPlaceholder(string $basename, ?string $label = null): string
    {
        $text = $label ?? str_replace('-', ' ', $basename);
        $encoded = urlencode(Str::limit($text, 22, ''));
        $url = 'https://placehold.co/500x500/F57C00/FFFFFF/png?text='.$encoded;
        $path = 'products/'.$basename.'.png';

        $downloaded = $this->download($url, $basename);
        if ($downloaded) {
            return $downloaded;
        }

        $minimal = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBEQCEAwEPwAB//9k=');
        Storage::disk('public')->put($path, $minimal);

        return $path;
    }

    private function isHealthyFile(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $bytes = @file_get_contents($absolutePath, false, null, 0, self::MIN_BYTES + 1);

        return $bytes !== false && $this->isHealthyBytes($bytes);
    }

    private function isHealthyBytes(string $bytes): bool
    {
        if (strlen($bytes) < self::MIN_BYTES) {
            return false;
        }

        $info = @getimagesizefromstring($bytes);

        return $info
            && ($info[0] ?? 0) >= self::MIN_DIMENSION
            && ($info[1] ?? 0) >= self::MIN_DIMENSION;
    }

    private function extensionFrom(string $url, ?string $contentType): string
    {
        if ($contentType && str_contains($contentType, 'png')) {
            return 'png';
        }
        if ($contentType && str_contains($contentType, 'webp')) {
            return 'webp';
        }
        if (preg_match('/\.(png|webp|jpe?g)(\?|$)/i', $url, $m)) {
            return strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
        }

        return 'jpg';
    }
}
