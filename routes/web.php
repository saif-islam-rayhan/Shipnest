<?php

use App\Http\Controllers\Account\AddressController as AccountAddressController;
use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Account\ReturnController as AccountReturnController;
use App\Http\Controllers\Account\ReviewController as AccountReviewController;
use App\Http\Controllers\Account\WishlistController as AccountWishlistController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\MerchantController as AdminMerchantController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\TwoFactorController as AdminTwoFactorController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Merchant\AnalyticsController as MerchantAnalyticsController;
use App\Http\Controllers\Merchant\DashboardController as MerchantDashboardController;
use App\Http\Controllers\Merchant\OrderController as MerchantOrderController;
use App\Http\Controllers\Merchant\ProductController as MerchantProductController;
use App\Http\Controllers\Merchant\ReturnController as MerchantReturnController;
use App\Http\Controllers\Merchant\SettingsController as MerchantSettingsController;
use App\Http\Controllers\Merchant\WalletController as MerchantWalletController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\PaymentWebhookController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ProductReviewController;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/newsletter', [HomeController::class, 'subscribe'])->name('newsletter.subscribe');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/search', [ProductController::class, 'search'])->name('search');
Route::get('/category/{slug}', [ProductController::class, 'category'])->name('category.show');
Route::get('/brand/{slug}', [ProductController::class, 'brand'])->name('brand.show');
Route::get('/product/{slug}', fn (string $slug) => redirect()->route('products.show', $slug))->name('product.show');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::post('/products/{product}/reviews', [ProductReviewController::class, 'store'])
    ->middleware(['auth', 'active'])
    ->name('products.reviews.store');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/order/success/{orderNumber}', [OrderController::class, 'success'])->name('order.success');

    Route::redirect('/orders', '/account/orders')->name('orders.index');
    Route::get('/orders/{order}', fn (Order $order) => redirect()->route('account.orders.show', $order->order_number))
        ->name('orders.show');

    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/', [AccountDashboardController::class, 'index'])->name('dashboard');
        Route::get('/orders', [AccountOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{orderNumber}', [AccountOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{orderNumber}/cancel', [AccountOrderController::class, 'cancel'])->name('orders.cancel');
        Route::get('/orders/{orderNumber}/invoice', [AccountOrderController::class, 'invoice'])->name('orders.invoice');
        Route::get('/returns', [AccountReturnController::class, 'index'])->name('returns.index');
        Route::post('/returns', [AccountReturnController::class, 'store'])->name('returns.store');
        Route::get('/wishlist', [AccountWishlistController::class, 'index'])->name('wishlist.index');
        Route::post('/wishlist/{product}', [AccountWishlistController::class, 'store'])->name('wishlist.store');
        Route::delete('/wishlist/{wishlist}', [AccountWishlistController::class, 'destroy'])->name('wishlist.destroy');
        Route::post('/wishlist/{wishlist}/move-to-cart', [AccountWishlistController::class, 'moveToCart'])->name('wishlist.move-to-cart');
        Route::get('/addresses', [AccountAddressController::class, 'index'])->name('addresses.index');
        Route::get('/addresses/create', [AccountAddressController::class, 'create'])->name('addresses.create');
        Route::post('/addresses', [AccountAddressController::class, 'store'])->name('addresses.store');
        Route::get('/addresses/{address}/edit', [AccountAddressController::class, 'edit'])->name('addresses.edit');
        Route::put('/addresses/{address}', [AccountAddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [AccountAddressController::class, 'destroy'])->name('addresses.destroy');
        Route::patch('/addresses/{address}/default', [AccountAddressController::class, 'setDefault'])->name('addresses.default');
        Route::get('/reviews', [AccountReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews', [AccountReviewController::class, 'store'])->name('reviews.store');
        Route::get('/profile', [AccountProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [AccountProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [AccountProfileController::class, 'updatePassword'])->name('profile.password');
        Route::patch('/profile/notifications', [AccountProfileController::class, 'updateNotifications'])->name('profile.notifications');
    });
});

Route::match(['get', 'post'], '/payment/callback/{gateway}', [OrderController::class, 'paymentCallback'])->name('payment.callback');
Route::post('/payment/webhook/stripe', [PaymentWebhookController::class, 'stripe'])->name('payment.webhook.stripe');
Route::post('/payment/ipn/{gateway}', [PaymentWebhookController::class, 'ipn'])->name('payment.ipn');

Route::prefix('merchant')->name('merchant.')->middleware(['auth', 'active', 'role:merchant'])->group(function () {
    Route::get('/shop/create', [MerchantDashboardController::class, 'createShop'])->name('shop.create');
    Route::post('/shop', [MerchantDashboardController::class, 'storeShop'])->name('shop.store');

    Route::middleware('merchant')->group(function () {
        Route::redirect('/', '/merchant/dashboard');
        Route::get('/dashboard', [MerchantDashboardController::class, 'index'])->name('dashboard');

        Route::get('/products', [MerchantProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [MerchantProductController::class, 'create'])->name('products.create');
        Route::post('/products', [MerchantProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [MerchantProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [MerchantProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [MerchantProductController::class, 'destroy'])->name('products.destroy');
        Route::patch('/products/{product}/toggle', [MerchantProductController::class, 'toggleStatus'])->name('products.toggle');
        Route::post('/products/{product}/duplicate', [MerchantProductController::class, 'duplicate'])->name('products.duplicate');
        Route::post('/products/bulk', [MerchantProductController::class, 'bulk'])->name('products.bulk');

        Route::get('/orders', [MerchantOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [MerchantOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/status', [MerchantOrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{order}/confirm', [MerchantOrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('/orders/{order}/ready', [MerchantOrderController::class, 'readyForPickup'])->name('orders.ready');
        Route::get('/orders/{order}/invoice', [MerchantOrderController::class, 'invoice'])->name('orders.invoice');

        Route::get('/returns', [MerchantReturnController::class, 'index'])->name('returns.index');
        Route::post('/returns/{return}/approve', [MerchantReturnController::class, 'approve'])->name('returns.approve');
        Route::post('/returns/{return}/reject', [MerchantReturnController::class, 'reject'])->name('returns.reject');

        Route::get('/wallet', [MerchantWalletController::class, 'index'])->name('wallet.index');
        Route::post('/wallet/withdraw', [MerchantWalletController::class, 'withdraw'])->name('wallet.withdraw');

        Route::get('/analytics', [MerchantAnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('/settings', [MerchantSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [MerchantSettingsController::class, 'update'])->name('settings.update');
    });
});

Route::post('/impersonate/stop', [AdminUserController::class, 'stopImpersonating'])->name('impersonate.stop')->middleware(['auth']);

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'role:super_admin,admin', 'admin.2fa'])->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');

    Route::get('/merchants', [AdminMerchantController::class, 'index'])->name('merchants.index');
    Route::get('/merchants/payouts', [AdminMerchantController::class, 'payouts'])->name('merchants.payouts');
    Route::get('/merchants/{merchant}', [AdminMerchantController::class, 'show'])->name('merchants.show');
    Route::patch('/merchants/{merchant}/approve', [AdminMerchantController::class, 'approve'])->name('merchants.approve');
    Route::post('/merchants/{merchant}/reject', [AdminMerchantController::class, 'reject'])->name('merchants.reject');
    Route::patch('/merchants/{merchant}/suspend', [AdminMerchantController::class, 'suspend'])->name('merchants.suspend');
    Route::patch('/merchants/{merchant}/commission', [AdminMerchantController::class, 'updateCommission'])->name('merchants.commission');
    Route::patch('/payouts/{payout}/approve', [AdminMerchantController::class, 'approvePayout'])->name('payouts.approve');
    Route::post('/payouts/{payout}/reject', [AdminMerchantController::class, 'rejectPayout'])->name('payouts.reject');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}/approve', [AdminProductController::class, 'approve'])->name('products.approve');
    Route::post('/products/{product}/reject', [AdminProductController::class, 'reject'])->name('products.reject');
    Route::patch('/products/{product}/feature', [AdminProductController::class, 'feature'])->name('products.feature');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{order}/disputes', [AdminOrderController::class, 'storeDispute'])->name('orders.disputes.store');
    Route::patch('/disputes/{dispute}', [AdminOrderController::class, 'resolveDispute'])->name('disputes.resolve');
    Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/categories/reorder', [AdminCategoryController::class, 'reorder'])->name('categories.reorder');
    Route::patch('/categories/{category}/featured', [AdminCategoryController::class, 'toggleFeatured'])->name('categories.featured');

    Route::get('/brands', [AdminBrandController::class, 'index'])->name('brands.index');
    Route::post('/brands', [AdminBrandController::class, 'store'])->name('brands.store');
    Route::put('/brands/{brand}', [AdminBrandController::class, 'update'])->name('brands.update');
    Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy'])->name('brands.destroy');
    Route::patch('/brands/{brand}/featured', [AdminBrandController::class, 'toggleFeatured'])->name('brands.featured');

    Route::get('/promotions', [AdminPromotionController::class, 'index'])->name('promotions.index');
    Route::post('/promotions/banners', [AdminPromotionController::class, 'storeBanner'])->name('promotions.banners.store');
    Route::delete('/promotions/banners/{banner}', [AdminPromotionController::class, 'destroyBanner'])->name('promotions.banners.destroy');
    Route::post('/promotions/flash-sales', [AdminPromotionController::class, 'storeFlashSale'])->name('promotions.flash-sales.store');
    Route::post('/promotions/flash-sales/{flashSale}/products', [AdminPromotionController::class, 'addFlashSaleProduct'])->name('promotions.flash-sales.products');
    Route::post('/promotions/coupons', [AdminPromotionController::class, 'storeCoupon'])->name('promotions.coupons.store');
    Route::delete('/promotions/coupons/{coupon}', [AdminPromotionController::class, 'destroyCoupon'])->name('promotions.coupons.destroy');

    Route::get('/finance', [AdminFinanceController::class, 'index'])->name('finance.index');

    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::patch('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');

    Route::get('/settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/maintenance', [AdminSettingController::class, 'toggleMaintenance'])->name('settings.maintenance');

    Route::get('/pages', [AdminPageController::class, 'index'])->name('pages.index');
    Route::get('/pages/create', [AdminPageController::class, 'create'])->name('pages.create');
    Route::post('/pages', [AdminPageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page}/edit', [AdminPageController::class, 'edit'])->name('pages.edit');
    Route::put('/pages/{page}', [AdminPageController::class, 'update'])->name('pages.update');
    Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])->name('pages.destroy');

    Route::get('/2fa/setup', [AdminTwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::post('/2fa/enable', [AdminTwoFactorController::class, 'enable'])->name('2fa.enable');
    Route::post('/2fa/disable', [AdminTwoFactorController::class, 'disable'])->name('2fa.disable');
    Route::get('/2fa/challenge', [AdminTwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa/verify', [AdminTwoFactorController::class, 'verify'])->name('2fa.verify');


    // Legacy redirects
    Route::redirect('/shops', '/admin/merchants?tab=pending');
    Route::patch('/shops/{shop}/approve', [AdminMerchantController::class, 'approve'])->name('shops.approve');
    Route::patch('/shops/{shop}/suspend', [AdminMerchantController::class, 'suspend'])->name('shops.suspend');
});
