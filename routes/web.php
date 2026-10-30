<?php

declare(strict_types=1);

use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentHeartController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HeartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\MessageAttachmentController;
use App\Http\Controllers\MessagesPageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SharePostMessageController;
use App\Http\Controllers\SharedPostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationInviteController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TimelineInvitationController;
use App\Http\Controllers\TimelineManagementController;
use App\Http\Controllers\TimelineUserController;
use App\Http\Controllers\TimelineUserRoleController;
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

Route::middleware('auth')->group(function (Router $router): void {
    $router->get('/messages', [MessagesPageController::class, 'index'])->name('messages.index');
    $router->get('/messages/{conversation}', [MessagesPageController::class, 'show'])->name('messages.show');
    $router->get('/messages/{conversation}/shared-posts/{message}', SharedPostController::class)->name('messages.shared-post');
    // JSON endpoints use the web session and CSRF protection; UI follows in Stage 2.
    $router->prefix('api/messages')->name('messaging.')->middleware('cache.headers:private;no_store')->group(function (Router $router): void {
        $router->get('/contacts', [MessagingController::class, 'contacts'])->name('contacts');
        $router->get('/conversations', [MessagingController::class, 'index'])->name('index');
        $router->post('/conversations', [MessagingController::class, 'store'])->middleware('throttle:20,1')->name('store');
        $router->get('/conversations/{conversation}', [MessagingController::class, 'show'])->name('show');
        $router->post('/conversations/{conversation}/messages', [MessagingController::class, 'send'])->middleware('throttle:60,1')->name('send');
        $router->put('/conversations/{conversation}/read', [MessagingController::class, 'read'])->middleware('throttle:120,1')->name('read');
        $router->post('/conversations/{conversation}/typing', [MessagingController::class, 'typing'])->middleware('throttle:90,1')->name('typing');
        $router->get('/session', [MessagingController::class, 'session'])->name('session');
        $router->post('/conversations/{conversation}/attachments', [MessageAttachmentController::class, 'store'])->middleware('throttle:12,1')->name('attachments.store');
        $router->get('/attachments/{attachment}', [MessageAttachmentController::class, 'show'])->name('attachments.show');
        $router->get('/conversations/{conversation}/messages/{message}', [MessagingController::class, 'message'])->name('message');
        $router->post('/posts/{postId}/share', SharePostMessageController::class)->whereNumber('postId')->middleware('throttle:20,1')->name('share-post');
    });

    $router->get(uri: '/', action: [HomeController::class, 'index'])->name(name: 'home');
    $router->get(uri: '/gallery', action: [GalleryController::class, 'index'])->name(name: 'gallery');
    $router->get(uri: '/profile', action: [ProfileController::class, 'edit'])->name(name: 'profile.edit');
    $router->get(uri: '/timeline/manage', action: TimelineManagementController::class)->name(name: 'timeline.manage');
    $router->put(uri: '/timeline', action: [TimelineController::class, 'update'])->name(name: 'timeline.update');
    $router->post(uri: '/timeline/invitations', action: [TimelineInvitationController::class, 'store'])->name(name: 'timeline.invitations.store');
    $router->put(uri: '/timeline/invitations/{registrationInvite}', action: [TimelineInvitationController::class, 'update'])->name(name: 'timeline.invitations.update');
    $router->post(uri: '/timeline/invitations/{registrationInvite}/notification', action: [TimelineInvitationController::class, 'notify'])->name(name: 'timeline.invitations.notify');
    $router->put(uri: '/timeline/users/{user}/role', action: [TimelineUserRoleController::class, 'update'])->name(name: 'timeline.users.role.update');
    $router->delete(uri: '/timeline/users/{user}', action: [TimelineUserController::class, 'destroy'])->name(name: 'timeline.users.destroy');
    $router->post(uri: '/posts', action: [PostController::class, 'store'])->name(name: 'posts.store');
    $router->get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    $router->get(uri: '/posts/{post}/edit', action: [PostController::class, 'edit'])->name(name: 'posts.edit');
    $router->patch(uri: '/posts/{post}', action: [PostController::class, 'update'])->name(name: 'posts.update');
    $router->delete(uri: '/posts/{post}', action: [PostController::class, 'destroy'])->name(name: 'posts.destroy');
    $router->post(uri: '/posts/{post}/comments', action: [CommentController::class, 'store'])->name(name: 'posts.comments.store');
    $router->post(uri: '/comments/{comment}/heart', action: [CommentHeartController::class, 'toggle'])->name(name: 'comments.hearts.toggle');
    $router->post(uri: '/posts/{post}/heart', action: [HeartController::class, 'toggle'])->name(name: 'posts.hearts.toggle');
    $router->get(uri: '/media/{media}', action: [MediaController::class, 'show'])->name(name: 'media.show');
    $router->get(uri: '/media/{media}/download', action: [MediaController::class, 'download'])->name(name: 'media.download');
});
