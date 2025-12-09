<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Laravel\Routing\ResourceRegistrar;
use App\Http\Controllers\Api\V2\Auth\LoginController;
use App\Http\Controllers\Api\V2\Auth\LogoutController;
use App\Http\Controllers\Api\V2\Auth\RegisterController;
use App\Http\Controllers\Api\V2\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V2\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V2\AllowedIpController;
use App\Http\Controllers\Api\V2\MeController;
use App\Http\Controllers\Api\V2\ProfileController;
use App\Http\Controllers\Api\V2\UpdatePasswordController;
use App\Http\Controllers\Api\V2\UserManagementController;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Http\Controllers\JsonApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v2')->middleware('json.api')->group(function () {
    Route::post('/login', LoginController::class)->name('login');
    Route::post('/logout', LogoutController::class)->middleware('auth.token');
    Route::post('/register', RegisterController::class);
    Route::post('/password-forgot', ForgotPasswordController::class);
    Route::post('/password-reset', ResetPasswordController::class)->name('password.reset');

    // Allowed IPs routes (admin only)
    Route::middleware('auth.token')->group(function () {
        // Authenticated profile
        Route::get('/profile', MeController::class);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::patch('/password', UpdatePasswordController::class);
        Route::get('/allowed-ips', [AllowedIpController::class, 'index']);
        Route::post('/allowed-ips', [AllowedIpController::class, 'store']);
    });
});

Route::prefix('v2')->middleware('auth.token')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::post('/users', [UserManagementController::class, 'store']);
    Route::put('/users/{id}', [UserManagementController::class, 'update']);
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
    Route::post('/allowed-ips/delete', [AllowedIpController::class, 'destroyByBody']);
});
