<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\FundingController;
use App\Http\Controllers\Web\MissionController;
use App\Http\Controllers\Web\OrganizationController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\SecurityController;
use App\Http\Controllers\Web\ModulePlaceholderController;
use App\Http\Controllers\Web\MissionConfigurationController;
use App\Http\Controllers\Web\ConfigurationWizardController;
use App\Http\Controllers\Web\ControlCenterController;
use App\Http\Controllers\Web\OrganizationConfigurationController;
use App\Http\Controllers\Web\StockController;
use App\Http\Controllers\Web\StructureController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\UserController as WebUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::redirect('/setup', '/configuration/organization')->name('setup.index');
    Route::get('/configuration', [ControlCenterController::class, 'show'])
        ->middleware('permission:configuration.view')->name('configuration.index');
    Route::get('/configuration/start/{flowType}', [ConfigurationWizardController::class, 'start'])
        ->whereIn('flowType', array_column(\App\Enums\ConfigurationFlowType::cases(), 'value'))
        ->middleware('permission:configuration.view')
        ->name('configuration.workflow.start');
    Route::post('/configuration/workflows/{workflow}/draft', [ConfigurationWizardController::class, 'saveDraft'])
        ->whereUuid('workflow')
        ->middleware('permission:configuration.view')
        ->name('configuration.workflow.draft');
    Route::get('/configuration/organization', [OrganizationConfigurationController::class, 'show'])->middleware('permission:configuration.view')->name('configuration.organization');
    Route::post('/configuration/organization', [OrganizationConfigurationController::class, 'save'])->middleware('permission:configuration.view')->name('configuration.organization.save');
    Route::put('/configuration/organization/{organization}', [OrganizationConfigurationController::class, 'update'])->middleware('permission:configuration.view')->name('configuration.organization.update');
    Route::delete('/configuration/organization/{organization}', [OrganizationConfigurationController::class, 'archive'])->middleware('permission:configuration.view')->name('configuration.organization.archive');
    Route::get('/configuration/mission', [MissionConfigurationController::class, 'show'])->middleware('permission:configuration.view')->name('configuration.mission');
    Route::post('/configuration/mission', [MissionConfigurationController::class, 'save'])->middleware('permission:configuration.view')->name('configuration.mission.save');
    Route::delete('/configuration/mission/{mission}/archive', [MissionConfigurationController::class, 'archive'])->middleware('permission:configuration.view')->name('configuration.mission.archive');
    Route::post('/configuration/mission/archived/{mission}/restore', [MissionConfigurationController::class, 'restore'])->middleware('permission:configuration.view')->name('configuration.mission.restore');
    Route::get('/configuration/{step}', [ConfigurationWizardController::class, 'show'])
        ->whereIn('step', array_keys(ConfigurationWizardController::STEPS))->middleware('permission:configuration.view')->name('configuration.step');
    Route::post('/configuration/{step}', [ConfigurationWizardController::class, 'save'])
        ->whereIn('step', array_keys(ConfigurationWizardController::STEPS))->middleware('permission:configuration.view')->name('configuration.step.save');
    Route::delete('/configuration/{step}/archive', [ConfigurationWizardController::class, 'archive'])
        ->whereIn('step', array_keys(ConfigurationWizardController::STEPS))->middleware('permission:configuration.view')->name('configuration.step.archive');
    Route::post('/configuration/{step}/archived/{id}/restore', [ConfigurationWizardController::class, 'restore'])
        ->whereIn('step', array_keys(ConfigurationWizardController::STEPS))->middleware('permission:configuration.view')->name('configuration.step.restore');
    Route::get('/control-center', [ControlCenterController::class, 'show'])->middleware('permission:configuration.view')->name('control-center');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/projects', [ModulePlaceholderController::class, 'show'])->defaults('module', 'projects')->middleware('permission:projects.view')->name('modules.projects');
    Route::get('/missions', [ModulePlaceholderController::class, 'show'])->defaults('module', 'missions')->middleware('permission:missions.view')->name('modules.missions');
    Route::get('/funding', [ModulePlaceholderController::class, 'show'])->defaults('module', 'funding')->middleware('permission:funding.view')->name('modules.funding');
    Route::get('/health-facilities', [ModulePlaceholderController::class, 'show'])->defaults('module', 'health-facilities')->middleware('permission:health_facilities.view')->name('modules.health-facilities');
    Route::get('/dispensing-sites', [ModulePlaceholderController::class, 'show'])->defaults('module', 'dispensing-sites')->middleware('permission:dispensing_sites.view')->name('modules.dispensing-sites');
    Route::get('/users', [WebUserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/standard-lists', [CatalogController::class, 'home'])->defaults('section', 'lists')->middleware('permission:standard_lists.view')->name('modules.standard-lists');
    Route::get('/products', [CatalogController::class, 'home'])->defaults('section', 'products')->middleware('permission:products.view')->name('modules.products');
    Route::get('/stocks', [StockController::class, 'home'])->middleware('permission:stocks.view')->name('modules.stocks');
    Route::get('/receipts', [ModulePlaceholderController::class, 'show'])->defaults('module', 'receipts')->middleware('permission:receipts.view')->name('modules.receipts');
    Route::get('/dispensations', [ModulePlaceholderController::class, 'show'])->defaults('module', 'dispensing')->middleware('permission:dispensing.view')->name('modules.dispensing');
    Route::get('/inventories', [ModulePlaceholderController::class, 'show'])->defaults('module', 'inventories')->middleware('permission:inventories.view')->name('modules.inventories');
    Route::get('/orders', [ModulePlaceholderController::class, 'show'])->defaults('module', 'orders')->middleware('permission:orders.view')->name('modules.orders');
    Route::get('/reports', [ModulePlaceholderController::class, 'show'])->defaults('module', 'reports')->middleware('permission:reports.view')->name('modules.reports');
    Route::get('/synchronization', [ModulePlaceholderController::class, 'show'])->defaults('module', 'synchronization')->middleware('permission:synchronization.view')->name('modules.synchronization');
    Route::get('/settings', [ModulePlaceholderController::class, 'show'])->defaults('module', 'settings')->middleware('permission:settings.view')->name('modules.settings');
    Route::get('/project-settings', [ModulePlaceholderController::class, 'show'])->defaults('module', 'project-settings')->middleware('permission:project_settings.view')->name('modules.project-settings');
    Route::get('/site-settings', [ModulePlaceholderController::class, 'show'])->defaults('module', 'site-settings')->middleware('permission:site_settings.view')->name('modules.site-settings');
    Route::get('/activity-log', [ModulePlaceholderController::class, 'show'])->defaults('module', 'activity-log')->middleware('permission:activity_logs.view')->name('modules.activity-log');
    Route::get('/activity-log-local', [ModulePlaceholderController::class, 'show'])->defaults('module', 'activity-log-local')->middleware('permission:activity_logs.view_local')->name('modules.activity-log-local');
    Route::get('/security', [SecurityController::class, 'index'])->middleware('permission:roles.manage')->name('security.index');
    Route::post('/security/roles', [SecurityController::class, 'store'])->middleware('permission:roles.manage')->name('security.roles.store');
    Route::put('/security/roles/{role}', [SecurityController::class, 'update'])->middleware('permission:roles.manage')->name('security.roles.update');
    Route::delete('/security/roles/{role}', [SecurityController::class, 'destroy'])->middleware('permission:roles.manage')->name('security.roles.destroy');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::delete('/profile/devices/{device}', [ProfileController::class, 'revoke'])->name('profile.devices.revoke');
    Route::delete('/profile/sessions/{session}', [ProfileController::class, 'revokeSession'])->name('profile.sessions.revoke');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/users/create', [WebUserController::class, 'create'])->name('users.create');
    Route::get('/users/archived', [WebUserController::class, 'archived'])->name('users.archived');
    Route::get('/users/archived/{user}', [WebUserController::class, 'showArchived'])->name('users.archived.show');
    Route::post('/users/archived/{user}/restore', [WebUserController::class, 'restore'])->name('users.restore');
    Route::post('/users', [AuthController::class, 'createUser'])->name('users.store');
    Route::get('/users/{user}', [WebUserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [WebUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [WebUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [WebUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/reset-password', [AuthController::class, 'resetUserPassword'])->name('users.reset-password');
});
Route::middleware('auth')->group(function () {
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations/archived/{organization}/restore', [OrganizationController::class, 'restore'])->name('organizations.restore');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
    Route::post('/countries', [MissionController::class, 'storeCountry'])->name('countries.store');
    Route::get('/organizations/{organization}/missions', [MissionController::class, 'index'])->name('organizations.missions.index');
    Route::post('/organizations/{organization}/missions', [MissionController::class, 'store'])->name('organizations.missions.store');
    Route::put('/organizations/{organization}/missions/{mission}', [MissionController::class, 'update'])->name('organizations.missions.update');
    Route::delete('/organizations/{organization}/missions/{mission}', [MissionController::class, 'destroy'])->name('organizations.missions.destroy');
    Route::post('/organizations/{organization}/missions/archived/{mission}/restore', [MissionController::class, 'restore'])->name('organizations.missions.restore');
    Route::get('/organizations/{organization}/projects', [ProjectController::class, 'index'])->name('organizations.projects.index');
    Route::post('/organizations/{organization}/projects', [ProjectController::class, 'store'])->name('organizations.projects.store');
    Route::put('/organizations/{organization}/projects/{project}', [ProjectController::class, 'update'])->name('organizations.projects.update');
    Route::delete('/organizations/{organization}/projects/{project}', [ProjectController::class, 'destroy'])->name('organizations.projects.destroy');
    Route::post('/organizations/{organization}/projects/archived/{project}/restore', [ProjectController::class, 'restore'])->name('organizations.projects.restore');
    Route::get('/organizations/{organization}/projects/{project}/funding', [FundingController::class, 'index'])->name('organizations.projects.funding.index');
    Route::post('/organizations/{organization}/donors', [FundingController::class, 'storeDonor'])->name('organizations.donors.store');
    Route::post('/organizations/{organization}/programs', [FundingController::class, 'storeProgram'])->name('organizations.programs.store');
    Route::put('/organizations/{organization}/donors/{donor}', [FundingController::class, 'updateDonor'])->name('organizations.donors.update');
    Route::delete('/organizations/{organization}/donors/{donor}', [FundingController::class, 'archiveDonor'])->name('organizations.donors.destroy');
    Route::post('/organizations/{organization}/donors/archived/{donor}/restore', [FundingController::class, 'restoreDonor'])->name('organizations.donors.restore');
    Route::put('/organizations/{organization}/programs/{program}', [FundingController::class, 'updateProgram'])->name('organizations.programs.update');
    Route::delete('/organizations/{organization}/programs/{program}', [FundingController::class, 'archiveProgram'])->name('organizations.programs.destroy');
    Route::post('/organizations/{organization}/programs/archived/{program}/restore', [FundingController::class, 'restoreProgram'])->name('organizations.programs.restore');
    Route::post('/organizations/{organization}/projects/{project}/donors', [FundingController::class, 'attachDonor'])->name('organizations.projects.donors.attach');
    Route::post('/organizations/{organization}/projects/{project}/programs', [FundingController::class, 'attachProgram'])->name('organizations.projects.programs.attach');
    Route::delete('/organizations/{organization}/projects/{project}/donors/{donor}', [FundingController::class, 'detachDonor'])->name('organizations.projects.donors.detach');
    Route::delete('/organizations/{organization}/projects/{project}/programs/{program}', [FundingController::class, 'detachProgram'])->name('organizations.projects.programs.detach');
    Route::get('/organizations/{organization}/structures', [StructureController::class, 'index'])->name('organizations.structures.index');
    Route::get('/organizations/{organization}/facilities/create', [StructureController::class, 'createFacility'])->name('organizations.facilities.create');
    Route::post('/organizations/{organization}/facilities', [StructureController::class, 'storeFacility'])->name('organizations.facilities.store');
    Route::put('/organizations/{organization}/facilities/{facility}', [StructureController::class, 'updateFacility'])->name('organizations.facilities.update');
    Route::delete('/organizations/{organization}/facilities/{facility}', [StructureController::class, 'archiveFacility'])->name('organizations.facilities.destroy');
    Route::post('/organizations/{organization}/facilities/archived/{facility}/restore', [StructureController::class, 'restoreFacility'])->name('organizations.facilities.restore');
    Route::post('/organizations/{organization}/facilities/{facility}/departments', [StructureController::class, 'storeDepartment'])->name('organizations.facilities.departments.store');
    Route::put('/organizations/{organization}/facilities/{facility}/departments/{department}', [StructureController::class, 'updateDepartment'])->name('organizations.facilities.departments.update');
    Route::delete('/organizations/{organization}/facilities/{facility}/departments/{department}', [StructureController::class, 'archiveDepartment'])->name('organizations.facilities.departments.destroy');
    Route::post('/organizations/{organization}/facilities/{facility}/departments/archived/{department}/restore', [StructureController::class, 'restoreDepartment'])->name('organizations.facilities.departments.restore');
    Route::post('/organizations/{organization}/facilities/{facility}/pharmacies', [StructureController::class, 'storePharmacy'])->name('organizations.facilities.pharmacies.store');
    Route::put('/organizations/{organization}/facilities/{facility}/pharmacies/{pharmacy}', [StructureController::class, 'updatePharmacy'])->name('organizations.facilities.pharmacies.update');
    Route::delete('/organizations/{organization}/facilities/{facility}/pharmacies/{pharmacy}', [StructureController::class, 'archivePharmacy'])->name('organizations.facilities.pharmacies.destroy');
    Route::post('/organizations/{organization}/facilities/{facility}/pharmacies/archived/{pharmacy}/restore', [StructureController::class, 'restorePharmacy'])->name('organizations.facilities.pharmacies.restore');
    Route::post('/organizations/{organization}/facilities/{facility}/sites', [StructureController::class, 'storeSite'])->name('organizations.facilities.sites.store');
    Route::put('/organizations/{organization}/facilities/{facility}/sites/{site}', [StructureController::class, 'updateSite'])->name('organizations.facilities.sites.update');
    Route::delete('/organizations/{organization}/facilities/{facility}/sites/{site}', [StructureController::class, 'archiveSite'])->name('organizations.facilities.sites.destroy');
    Route::post('/organizations/{organization}/facilities/{facility}/sites/archived/{site}/restore', [StructureController::class, 'restoreSite'])->name('organizations.facilities.sites.restore');
    Route::put('/organizations/{organization}/module-activations', [StructureController::class, 'activation'])->name('organizations.module-activations.update');
    Route::get('/organizations/{organization}/catalog', [CatalogController::class, 'index'])->name('organizations.catalog.index');
    Route::get('/organizations/{o}/catalog/products/create', [CatalogController::class, 'createProduct'])->name('organizations.catalog.products.create');
    Route::get('/organizations/{organization}/stocks', [StockController::class, 'index'])->name('organizations.stocks.index');
    Route::get('/organizations/{organization}/stocks/movements/create', [StockController::class, 'createMovement'])->name('organizations.stocks.movements.create');
    Route::post('/organizations/{organization}/stocks/movements', [StockController::class, 'storeMovement'])->name('organizations.stocks.movements.store');
    Route::post('/organizations/{organization}/stocks/movements/{movement}/compensate', [StockController::class, 'compensate'])->name('organizations.stocks.movements.compensate');
    Route::get('/organizations/{organization}/receipts', [ReceiptController::class, 'index'])->name('organizations.receipts.index');
    Route::post('/organizations/{organization}/receipts', [ReceiptController::class, 'store'])->name('organizations.receipts.store');
    Route::get('/organizations/{organization}/receipts/{receipt}', [ReceiptController::class, 'show'])->name('organizations.receipts.show');
    Route::post('/organizations/{organization}/receipts/{receipt}/validate', [ReceiptController::class, 'validateReceipt'])->name('organizations.receipts.validate');
    Route::post('/organizations/{o}/catalog/references', [CatalogController::class, 'storeReference'])->name('organizations.catalog.references.store');
    Route::put('/organizations/{o}/catalog/references/{m}', [CatalogController::class, 'updateReference'])->name('organizations.catalog.references.update');
    Route::delete('/organizations/{o}/catalog/references/{m}', [CatalogController::class, 'archiveReference'])->name('organizations.catalog.references.destroy');
    Route::post('/organizations/{o}/catalog/references/archived/{id}/restore', [CatalogController::class, 'restoreReference'])->name('organizations.catalog.references.restore');
    Route::post('/organizations/{o}/catalog/suppliers', [CatalogController::class, 'storeSupplier'])->name('organizations.catalog.suppliers.store');
    Route::put('/organizations/{o}/catalog/suppliers/{m}', [CatalogController::class, 'updateSupplier'])->name('organizations.catalog.suppliers.update');
    Route::delete('/organizations/{o}/catalog/suppliers/{m}', [CatalogController::class, 'archiveSupplier'])->name('organizations.catalog.suppliers.destroy');
    Route::post('/organizations/{o}/catalog/suppliers/archived/{id}/restore', [CatalogController::class, 'restoreSupplier'])->name('organizations.catalog.suppliers.restore');
    Route::post('/organizations/{o}/catalog/products', [CatalogController::class, 'storeProduct'])->name('organizations.catalog.products.store');
    Route::put('/organizations/{o}/catalog/products/{m}', [CatalogController::class, 'updateProduct'])->name('organizations.catalog.products.update');
    Route::delete('/organizations/{o}/catalog/products/{m}', [CatalogController::class, 'archiveProduct'])->name('organizations.catalog.products.destroy');
    Route::post('/organizations/{o}/catalog/products/archived/{id}/restore', [CatalogController::class, 'restoreProduct'])->name('organizations.catalog.products.restore');
    Route::post('/organizations/{o}/catalog/batches', [CatalogController::class, 'storeBatch'])->name('organizations.catalog.batches.store');
    Route::put('/organizations/{o}/catalog/batches/{m}', [CatalogController::class, 'updateBatch'])->name('organizations.catalog.batches.update');
    Route::delete('/organizations/{o}/catalog/batches/{m}', [CatalogController::class, 'archiveBatch'])->name('organizations.catalog.batches.destroy');
    Route::post('/organizations/{o}/catalog/batches/archived/{id}/restore', [CatalogController::class, 'restoreBatch'])->name('organizations.catalog.batches.restore');
    Route::post('/organizations/{o}/catalog/kits', [CatalogController::class, 'storeKit'])->name('organizations.catalog.kits.store');
    Route::put('/organizations/{o}/catalog/kits/{m}', [CatalogController::class, 'updateKit'])->name('organizations.catalog.kits.update');
    Route::delete('/organizations/{o}/catalog/kits/{m}', [CatalogController::class, 'archiveKit'])->name('organizations.catalog.kits.destroy');
    Route::post('/organizations/{o}/catalog/kits/archived/{id}/restore', [CatalogController::class, 'restoreKit'])->name('organizations.catalog.kits.restore');
    Route::post('/organizations/{o}/catalog/lists', [CatalogController::class, 'storeList'])->name('organizations.catalog.lists.store');
    Route::put('/organizations/{o}/catalog/lists/{m}', [CatalogController::class, 'updateList'])->name('organizations.catalog.lists.update');
    Route::delete('/organizations/{o}/catalog/lists/{m}', [CatalogController::class, 'archiveList'])->name('organizations.catalog.lists.destroy');
    Route::post('/organizations/{o}/catalog/lists/archived/{id}/restore', [CatalogController::class, 'restoreList'])->name('organizations.catalog.lists.restore');
    Route::post('/organizations/{o}/catalog/lists/{list}/versions', [CatalogController::class, 'newVersion'])->name('organizations.catalog.lists.versions.store');
    Route::post('/organizations/{o}/catalog/lists/{list}/versions/{version}/publish', [CatalogController::class, 'publish'])->name('organizations.catalog.lists.versions.publish');
    Route::get('/organizations/{o}/catalog/products/export', [CatalogController::class, 'exportProducts'])->name('organizations.catalog.products.export');
    Route::post('/organizations/{o}/catalog/products/import', [CatalogController::class, 'importProducts'])->name('organizations.catalog.products.import');
    Route::get('/organizations/{o}/catalog/products/export-xlsx', [CatalogController::class, 'exportProductsExcel'])->name('organizations.catalog.products.export-xlsx');
    Route::post('/organizations/{o}/catalog/products/import-xlsx', [CatalogController::class, 'importProductsExcel'])->name('organizations.catalog.products.import-xlsx');
});
