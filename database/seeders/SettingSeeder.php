<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'site_name', 'value' => 'ShipNest', 'group' => 'general'],
            ['key' => 'site_tagline', 'value' => 'Bangladesh\'s Leading Online Marketplace', 'group' => 'general'],
            ['key' => 'site_logo', 'value' => 'settings/logo.png', 'group' => 'general'],
            ['key' => 'site_favicon', 'value' => 'settings/favicon.ico', 'group' => 'general'],
            ['key' => 'contact_email', 'value' => 'support@shipnest.com', 'group' => 'contact'],
            ['key' => 'contact_phone', 'value' => '+880 1700-000000', 'group' => 'contact'],
            ['key' => 'contact_address', 'value' => 'Gulshan Avenue, Dhaka 1212, Bangladesh', 'group' => 'contact'],
            ['key' => 'social_facebook', 'value' => 'https://facebook.com/shipnest', 'group' => 'social'],
            ['key' => 'social_instagram', 'value' => 'https://instagram.com/shipnest', 'group' => 'social'],
            ['key' => 'social_twitter', 'value' => 'https://twitter.com/shipnest', 'group' => 'social'],
            ['key' => 'social_youtube', 'value' => 'https://youtube.com/shipnest', 'group' => 'social'],
            ['key' => 'meta_description', 'value' => 'Shop online at ShipNest - electronics, fashion, home & more with fast delivery across Bangladesh.', 'group' => 'seo'],
            ['key' => 'meta_keywords', 'value' => 'online shopping, ecommerce, bangladesh, shipnest', 'group' => 'seo'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']]
            );
        }
    }
}
