<?php
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
Route::prefix('v1')->group(function():void{
 Route::get('/health',fn():JsonResponse=>response()->json(['service'=>'sagastock-api','status'=>'ok','version'=>'v1']))->name('api.v1.health');
 Route::middleware('throttle:6,1')->group(function(){Route::post('/auth/login',[AuthController::class,'login']);Route::post('/auth/forgot-password',[PasswordResetController::class,'forgot']);Route::post('/auth/reset-password',[PasswordResetController::class,'reset']);});
 Route::middleware('auth:sanctum')->group(function():void{
  Route::get('/auth/me',[AuthController::class,'me']);Route::post('/auth/logout',[AuthController::class,'logout']);Route::put('/auth/password',[PasswordController::class,'update']);Route::get('/auth/devices',[DeviceController::class,'index']);Route::delete('/auth/devices/{device}',[DeviceController::class,'revoke']);
  Route::get('/roles',[UserController::class,'roles'])->middleware('permission:users.view'); Route::get('/security/roles',[RoleController::class,'index'])->middleware('permission:roles.manage'); Route::post('/security/roles',[RoleController::class,'store'])->middleware('permission:roles.manage'); Route::put('/security/roles/{role}',[RoleController::class,'update'])->middleware('permission:roles.manage'); Route::delete('/security/roles/{role}',[RoleController::class,'destroy'])->middleware('permission:roles.manage'); Route::get('/security/audits',[AuditController::class,'index'])->middleware('permission:audit.view');Route::get('/users',[UserController::class,'index'])->middleware('permission:users.view');Route::post('/users',[UserController::class,'store'])->middleware('permission:users.manage');Route::put('/users/{user}',[UserController::class,'update'])->middleware('permission:users.manage');Route::post('/users/{user}/reset-password',[UserController::class,'resetPassword'])->middleware('permission:users.manage');
  Route::get('/organizations',[OrganizationController::class,'index'])->middleware('permission:organizations.view');
  Route::get('/organizations/{organization}',[OrganizationController::class,'show'])->middleware('permission:organizations.view');
  Route::post('/organizations',[OrganizationController::class,'store'])->middleware('permission:organizations.manage');
  Route::put('/organizations/{organization}',[OrganizationController::class,'update'])->middleware('permission:organizations.manage');
  Route::delete('/organizations/{organization}',[OrganizationController::class,'destroy'])->middleware('permission:organizations.manage');
 });
});

