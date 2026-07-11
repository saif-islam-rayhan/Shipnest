<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Market\AiDesignStudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AiDesignController extends Controller
{
    public function index(): View
    {
        return view('admin.ai-design.index', [
            'generateUrl' => route('admin.ai-design.generate'),
            'createProductUrl' => route('admin.products.create'),
        ]);
    }

    public function generate(Request $request, AiDesignStudioService $studio): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', AiDesignStudioService::MODES)],
            'prompt' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $studio->handle(
                $validated['mode'],
                trim((string) ($validated['prompt'] ?? '')),
                null,
            );
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
