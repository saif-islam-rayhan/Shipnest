<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class AgentBranding
{
    public static function name(): string
    {
        return (string) config('shipnest.agent.name', 'ShipNest AI');
    }

    public static function logoUrl(): ?string
    {
        $path = config('shipnest.agent.logo');

        return filled($path) ? Storage::disk('public')->url($path) : null;
    }
}
