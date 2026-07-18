<?php

declare(strict_types=1);

use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentHeartController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HeartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationInviteController;
use App\Http\Controllers\SetupController;
use App\Http\Middleware\EnsureApplicationIsNotSetup;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureApplicationIsNotSetup::class, 'guest'])->group(function (Router $router): void {
    $router->get(uri: '/setup', action: [SetupController::class, 'show'])
        ->name(name: 'setup.show');

    $router->post(uri: '/setup', action: [SetupController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name(name: 'setup.store');
});

/**
 * Unique registration url which is generated using the "php artisan users:create"
 * command. Link is deactivated after use.
 */
Route::middleware('guest')
    ->get(uri: '/register/invite/{token}', action: RegistrationInviteController::class)
    ->name(name: 'register.invite');

/**
 * All users must be logged in to access any of the features of
 * the site. Therefore, all routes must be verified unless they
 * access any of the base auth routes such as 'login' or 'register'
 */
Route::middleware(['auth', 'verified'])->group(function (Router $router): void {
    $router->get(uri: '/', action: [HomeController::class, 'index'])->name(name: 'home');
    $router->get(uri: '/gallery', action: [GalleryController::class, 'index'])->name(name: 'gallery');
    $router->get(uri: '/profile', action: [ProfileController::class, 'edit'])->name(name: 'profile.edit');
    $router->post(uri: '/posts', action: [PostController::class, 'store'])->name(name: 'posts.store');
    $router->get(uri: '/posts/{post}/edit', action: [PostController::class, 'edit'])->name(name: 'posts.edit');
    $router->patch(uri: '/posts/{post}', action: [PostController::class, 'update'])->name(name: 'posts.update');
    $router->delete(uri: '/posts/{post}', action: [PostController::class, 'destroy'])->name(name: 'posts.destroy');
    $router->post(uri: '/posts/{post}/comments', action: [CommentController::class, 'store'])->name(name: 'posts.comments.store');
    $router->post(uri: '/comments/{comment}/heart', action: [CommentHeartController::class, 'toggle'])->name(name: 'comments.hearts.toggle');
    $router->post(uri: '/posts/{post}/heart', action: [HeartController::class, 'toggle'])->name(name: 'posts.hearts.toggle');
    $router->get(uri: '/media/{media}', action: [MediaController::class, 'show'])->name(name: 'media.show');
    $router->get(uri: '/media/{media}/download', action: [MediaController::class, 'download'])->name(name: 'media.download');
});
