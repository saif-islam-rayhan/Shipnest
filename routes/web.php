<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Merchant\DashboardController as MerchantDashboardController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/newsletter', [HomeController::class, 'subscribe'])->name('newsletter.subscribe');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

Route::get('/payment/callback/{gateway}', [OrderController::class, 'paymentCallback'])->name('payment.callback');

Route::prefix('merchant')->name('merchant.')->middleware(['auth', 'active', 'role:merchant', 'merchant.approved'])->group(function () {
    Route::get('/', [MerchantDashboardController::class, 'index'])->name('dashboard');
    Route::get('/shop/create', [MerchantDashboardController::class, 'createShop'])->name('shop.create');
    Route::post('/shop', [MerchantDashboardController::class, 'storeShop'])->name('shop.store');
    Route::get('/products', [MerchantDashboardController::class, 'products'])->name('products.index');
    Route::get('/products/create', [MerchantDashboardController::class, 'createProduct'])->name('products.create');
    Route::post('/products', [MerchantDashboardController::class, 'storeProduct'])->name('products.store');
    Route::get('/orders', [MerchantDashboardController::class, 'orders'])->name('orders.index');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'role:super_admin,admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/shops', [AdminDashboardController::class, 'shops'])->name('shops.index');
    Route::patch('/shops/{shop}/approve', [AdminDashboardController::class, 'approveShop'])->name('shops.approve');
    Route::patch('/shops/{shop}/suspend', [AdminDashboardController::class, 'suspendShop'])->name('shops.suspend');
    Route::get('/orders', [AdminDashboardController::class, 'orders'])->name('orders.index');
    Route::patch('/orders/{order}/status', [AdminDashboardController::class, 'updateOrderStatus'])->name('orders.status');
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users.index');
});
