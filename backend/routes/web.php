<?php
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SecurityController;
use App\Http\Controllers\Web\OrganizationController;
use Illuminate\Support\Facades\Route;
Route::redirect('/','/login');
Route::middleware('guest')->group(function(){
 Route::get('/login',[AuthController::class,'create'])->name('login');Route::post('/login',[AuthController::class,'store'])->name('login.store');
 Route::get('/forgot-password',[PasswordResetController::class,'request'])->name('password.request');Route::post('/forgot-password',[PasswordResetController::class,'email'])->name('password.email');
 Route::get('/reset-password/{token}',[PasswordResetController::class,'resetForm'])->name('password.reset');Route::post('/reset-password',[PasswordResetController::class,'reset'])->name('password.update');
});
Route::middleware('auth')->group(function(){Route::get('/dashboard',[AuthController::class,'dashboard'])->name('dashboard'); Route::get('/security',[SecurityController::class,'index'])->name('security.index'); Route::post('/security/roles',[SecurityController::class,'store'])->name('security.roles.store'); Route::put('/security/roles/{role}',[SecurityController::class,'update'])->name('security.roles.update'); Route::delete('/security/roles/{role}',[SecurityController::class,'destroy'])->name('security.roles.destroy');Route::get('/profile',[ProfileController::class,'show'])->name('profile.show');Route::put('/profile/password',[ProfileController::class,'password'])->name('profile.password');Route::delete('/profile/devices/{device}',[ProfileController::class,'revoke'])->name('profile.devices.revoke');Route::post('/logout',[AuthController::class,'destroy'])->name('logout');Route::post('/users',[AuthController::class,'createUser'])->name('users.store');Route::put('/users/{user}',[AuthController::class,'updateUser'])->name('users.update');Route::post('/users/{user}/reset-password',[AuthController::class,'resetUserPassword'])->name('users.reset-password');});
Route::middleware('auth')->group(function(){
 Route::get('/organizations',[OrganizationController::class,'index'])->name('organizations.index');
 Route::post('/organizations',[OrganizationController::class,'store'])->name('organizations.store');
 Route::put('/organizations/{organization}',[OrganizationController::class,'update'])->name('organizations.update');
 Route::delete('/organizations/{organization}',[OrganizationController::class,'destroy'])->name('organizations.destroy');
});

