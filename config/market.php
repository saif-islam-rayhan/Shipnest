<?php

return [
    'github_token' => env('GITHUB_TOKEN', ''),
    'github_models_endpoint' => env('GITHUB_MODELS_ENDPOINT', 'https://models.inference.ai.azure.com'),
    'use_live_llm' => filter_var(env('USE_LIVE_LLM', false), FILTER_VALIDATE_BOOLEAN),
    'model_google_search' => env('MODEL_GOOGLE_SEARCH', 'gpt-4o-mini'),
    'model_vision' => env('MODEL_VISION', env('MODEL_GOOGLE_SEARCH', 'gpt-4o-mini')),
    'tavily_api_key' => env('TAVILY_API_KEY', ''),
    'serpapi_key' => env('SERPAPI_KEY', ''),
    'use_google_ai_mode' => filter_var(env('USE_GOOGLE_AI_MODE', true), FILTER_VALIDATE_BOOLEAN),
    'google_ai_mode_gl' => env('GOOGLE_AI_MODE_GL', 'bd'),
    'google_ai_mode_hl' => env('GOOGLE_AI_MODE_HL', 'en'),
    'google_ai_mode_location' => env('GOOGLE_AI_MODE_LOCATION', 'Dhaka, Bangladesh'),
    'search_backend' => env('SEARCH_BACKEND', 'duckduckgo'),
    'searxng_url' => env('SEARXNG_URL', ''),
    'google_search_max_results' => (int) env('GOOGLE_SEARCH_MAX_RESULTS', 15),
    'trending_search_min_results' => (int) env('TRENDING_SEARCH_MIN_RESULTS', 5),
    'trending_search_target_results' => (int) env('TRENDING_SEARCH_TARGET_RESULTS', 15),
    'validation_max_retries' => (int) env('VALIDATION_MAX_RETRIES', 3),
    'default_top_n' => 5,
    'run_mode' => env('RUN_MODE', 'demo'),
    'mock_data_path' => storage_path('mock'),

    'site_selector_count' => 5,

    'site_registry' => [
        'daraz' => [
            'label' => 'Daraz',
            'domain' => 'daraz.com.bd',
            'search_url' => 'https://www.daraz.com.bd/catalog/?q={query}',
        ],
        'pickaboo' => [
            'label' => 'Pickaboo',
            'domain' => 'pickaboo.com',
            'search_url' => 'https://www.pickaboo.com/catalogsearch/result/?q={query}',
        ],
        'shajgoj' => [
            'label' => 'Shajgoj',
            'domain' => 'shajgoj.com',
            'search_url' => 'https://shop.shajgoj.com/?s={query}&post_type=product',
        ],
        'othoba' => [
            'label' => 'Othoba',
            'domain' => 'othoba.com',
            'search_url' => 'https://othoba.com/search?q={query}',
        ],
        'ajkerdeal' => [
            'label' => 'Ajkerdeal',
            'domain' => 'ajkerdeal.com',
            'search_url' => 'https://www.ajkerdeal.com/search.aspx?q={query}',
        ],
        'bikroy' => [
            'label' => 'Bikroy',
            'domain' => 'bikroy.com',
            'search_url' => 'https://bikroy.com/en/ads/bangladesh?q={query}',
        ],
        'facebook' => [
            'label' => 'Facebook Marketplace',
            'domain' => 'facebook.com',
            'search_url' => 'https://www.facebook.com/marketplace/search/?query={query}',
        ],
        'alibaba' => [
            'label' => 'Alibaba',
            'domain' => 'alibaba.com',
            'search_url' => 'https://www.alibaba.com/trade/search?SearchText={query}',
        ],
    ],

    'month_names' => [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ],

    'month_names_bn' => [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
        5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
        9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
    ],

    'sites' => ['daraz', 'bikroy', 'shajgoj', 'facebook', 'pickaboo', 'othoba', 'ajkerdeal', 'alibaba'],

    'categories' => [
        'fashion' => [
            'label' => 'Fashion',
            'label_bn' => 'ফ্যাশন / পোশাক',
            'sites' => ['daraz', 'shajgoj', 'pickaboo', 'othoba', 'bikroy'],
            'trending_keywords' => ['kurti', 'tops', 't-shirt', 'cotton saree', 'panjabi', 'kurta', 'leggings', 'hoodie'],
            'budget_products' => [
                ['product' => 'socks pack', 'price_min' => 200, 'price_max' => 600],
                ['product' => 'cap', 'price_min' => 150, 'price_max' => 500],
                ['product' => 'scarf', 'price_min' => 200, 'price_max' => 800],
                ['product' => 't-shirt', 'price_min' => 300, 'price_max' => 900],
                ['product' => 'tops', 'price_min' => 350, 'price_max' => 1200],
                ['product' => 'kurti', 'price_min' => 400, 'price_max' => 1500],
                ['product' => 'leggings', 'price_min' => 400, 'price_max' => 1000],
                ['product' => 'blouse', 'price_min' => 400, 'price_max' => 1500],
                ['product' => 'palazzo', 'price_min' => 500, 'price_max' => 1500],
                ['product' => 'panjabi', 'price_min' => 800, 'price_max' => 2500],
                ['product' => 'kurta', 'price_min' => 600, 'price_max' => 2000],
                ['product' => 'salwar kameez', 'price_min' => 800, 'price_max' => 2500],
                ['product' => 'hoodie', 'price_min' => 800, 'price_max' => 2500],
                ['product' => 'cotton saree', 'price_min' => 1200, 'price_max' => 4000],
                ['product' => 'winter jacket', 'price_min' => 1500, 'price_max' => 5000],
                ['product' => 'formal shirt', 'price_min' => 800, 'price_max' => 3000],
                ['product' => 'denim jeans', 'price_min' => 1000, 'price_max' => 3500],
            ],
        ],
        'electronics' => [
            'label' => 'Electronics',
            'label_bn' => 'ইলেকট্রনিক্স',
            'sites' => ['pickaboo', 'daraz', 'ajkerdeal', 'othoba', 'facebook'],
            'trending_keywords' => ['wireless earbuds', 'power bank', 'smart watch', 'gaming mouse', 'fast charger'],
            'budget_products' => [
                ['product' => 'USB cable', 'price_min' => 100, 'price_max' => 400],
                ['product' => 'screen guard', 'price_min' => 100, 'price_max' => 350],
                ['product' => 'phone case', 'price_min' => 150, 'price_max' => 800],
                ['product' => 'wired earphone', 'price_min' => 150, 'price_max' => 700],
                ['product' => 'fast charger', 'price_min' => 250, 'price_max' => 900],
                ['product' => 'wireless earbuds', 'price_min' => 800, 'price_max' => 3500],
                ['product' => 'power bank', 'price_min' => 600, 'price_max' => 3000],
                ['product' => 'gaming mouse', 'price_min' => 500, 'price_max' => 2500],
                ['product' => 'TWS earbuds', 'price_min' => 1000, 'price_max' => 4500],
                ['product' => 'smart band', 'price_min' => 1500, 'price_max' => 4000],
            ],
        ],
        'beauty' => [
            'label' => 'Beauty',
            'label_bn' => 'বিউটি / কসমেটিক্স',
            'sites' => ['shajgoj', 'daraz', 'othoba', 'pickaboo', 'facebook'],
            'trending_keywords' => ['perfume', 'lipstick', 'face wash', 'vitamin C serum', 'sunscreen'],
            'budget_products' => [
                ['product' => 'lip balm', 'price_min' => 150, 'price_max' => 500],
                ['product' => 'face wash', 'price_min' => 200, 'price_max' => 800],
                ['product' => 'lipstick', 'price_min' => 300, 'price_max' => 1200],
                ['product' => 'sunscreen', 'price_min' => 400, 'price_max' => 1500],
                ['product' => 'perfume', 'price_min' => 800, 'price_max' => 3500],
            ],
        ],
        'home' => [
            'label' => 'Home & Living',
            'label_bn' => 'হোম / লিভিং',
            'sites' => ['othoba', 'daraz', 'ajkerdeal', 'facebook'],
            'trending_keywords' => ['room heater', 'rechargeable fan', 'blanket', 'mosquito net', 'curtain'],
            'budget_products' => [
                ['product' => 'mosquito net', 'price_min' => 400, 'price_max' => 1200],
                ['product' => 'blanket', 'price_min' => 600, 'price_max' => 2500],
                ['product' => 'rechargeable fan', 'price_min' => 800, 'price_max' => 3000],
                ['product' => 'room heater', 'price_min' => 1500, 'price_max' => 5000],
            ],
        ],
        'kids' => [
            'label' => 'Kids & Baby',
            'label_bn' => 'কিডস / বেবি',
            'sites' => ['daraz', 'othoba', 'bikroy', 'facebook'],
            'trending_keywords' => ['baby dress', 'school bag', 'toy', 'diaper', 'kids jacket'],
            'budget_products' => [
                ['product' => 'toy car', 'price_min' => 200, 'price_max' => 800],
                ['product' => 'diaper', 'price_min' => 400, 'price_max' => 1200],
                ['product' => 'baby dress', 'price_min' => 400, 'price_max' => 1500],
                ['product' => 'school bag', 'price_min' => 500, 'price_max' => 2000],
            ],
        ],
        'food' => [
            'label' => 'Food & Grocery',
            'label_bn' => 'ফুড / গ্রোসারি',
            'sites' => ['othoba', 'daraz', 'facebook'],
            'trending_keywords' => ['honey', 'dates', 'rice', 'spice', 'dry food'],
            'budget_products' => [
                ['product' => 'honey', 'price_min' => 300, 'price_max' => 1200],
                ['product' => 'dates', 'price_min' => 300, 'price_max' => 1000],
                ['product' => 'rice 5kg', 'price_min' => 400, 'price_max' => 800],
            ],
        ],
        'import' => [
            'label' => 'Import / Wholesale',

            'label_bn' => 'ইম্পোর্ট / পাইকারি',
            'sites' => ['alibaba', 'daraz', 'bikroy'],
            'trending_keywords' => ['wholesale jacket', 'bulk earbuds', 'LED bulb pack'],
            'budget_products' => [
                ['product' => 'phone case pack', 'price_min' => 500, 'price_max' => 3000],
                ['product' => 'bulk earbuds', 'price_min' => 2000, 'price_max' => 8000],
            ],
        ],
    ],
];
