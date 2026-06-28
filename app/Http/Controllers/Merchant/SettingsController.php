<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Concerns\InteractsWithShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use InteractsWithShop;

    public function edit(Request $request): View
    {
        return view('merchant.settings.edit', ['shop' => $this->shop($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);

        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'nid_number' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
            'trade_license' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($request->hasFile('logo')) {
            if ($shop->logo) {
                Storage::disk('public')->delete($shop->logo);
            }
            $data['logo'] = $request->file('logo')->store('shops/logos', 'public');
        }

        if ($request->hasFile('banner')) {
            if ($shop->banner) {
                Storage::disk('public')->delete($shop->banner);
            }
            $data['banner'] = $request->file('banner')->store('shops/banners', 'public');
        }

        if ($request->hasFile('trade_license')) {
            if ($shop->trade_license && ! str_starts_with($shop->trade_license, 'shops/')) {
                Storage::disk('public')->delete($shop->trade_license);
            }
            $data['trade_license'] = $request->file('trade_license')->store('shops/documents', 'public');
        }

        $shop->update([
            'shop_name' => $data['shop_name'],
            'shop_slug' => Str::slug($data['shop_name']),
            'description' => $data['description'] ?? $shop->description,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'district' => $data['district'] ?? null,
            'nid_number' => $data['nid_number'] ?? null,
            'logo' => $data['logo'] ?? $shop->logo,
            'banner' => $data['banner'] ?? $shop->banner,
            'trade_license' => $data['trade_license'] ?? $shop->trade_license,
        ]);

        return back()->with('success', 'Store settings updated.');
    }
}
