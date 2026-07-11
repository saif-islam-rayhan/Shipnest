<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Concerns\InteractsWithShop;
use App\Services\Market\AiDesignStudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AiDesignController extends Controller
{
    use InteractsWithShop;

    public function index(): View
    {
        return view('merchant.ai-design.index', [
            'createProductUrl' => route('merchant.products.create'),
        ]);
    }

    public function generate(Request $request, AiDesignStudioService $studio): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', AiDesignStudioService::MODES)],
            'prompt' => ['nullable', 'string', 'max:500'],
        ]);

        $mode = $validated['mode'];
        $prompt = trim((string) ($validated['prompt'] ?? ''));

        try {
            $result = $studio->handle($mode, $prompt, $this->shop($request));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Request failed. Please try again.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'mode' => $result['mode'],
            'description' => $result['description'],
            'image_url' => $result['image_url'],
            'image_path' => $result['image_path'],
            'products' => $result['products'],
        ]);
    }
}
