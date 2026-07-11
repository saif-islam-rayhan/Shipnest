<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Market\ProductDescriptionGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait GeneratesProductDescriptions
{
    public function generateDescription(Request $request, ProductDescriptionGenerator $generator): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'attributes' => ['nullable', 'array', 'max:30'],
            'attributes.*.name' => ['nullable', 'string', 'max:100'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $description = $generator->generate($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not generate description. Try again.'], 500);
        }

        return response()->json([
            'description' => $description,
        ]);
    }
}
