<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReservationMessageController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetAssignmentController;
use App\Http\Controllers\AssetStatusController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\KitController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\CheckoutFormController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SnipeItBackupController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScanSessionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\ConsommableController;
use App\Http\Controllers\ConsommableRequestController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\AssetFileController;
use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Rafraîchissement du token CSRF (utilisé par l'intercepteur axios pour gérer les 419)
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Assets
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');

    // Routes admin sans paramètre — AVANT {asset} pour éviter le conflit de routing
    Route::middleware('role:admin')->group(function () {
        Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
        Route::post('/assets/bulk-delete', [AssetController::class, 'bulkDelete'])->name('assets.bulk-delete');
        Route::post('/assets/bulk-update', [AssetController::class, 'bulkUpdate'])->name('assets.bulk-update');
    });

    // Reservation admin routes WITHOUT {reservation} param — BEFORE parameterized routes
    Route::middleware('role:admin')->group(function () {
        Route::post('/reservations/bulk-update', [ReservationController::class, 'bulkUpdate'])->name('reservations.bulk-update');
        Route::post('/reservations/bulk-delete', [ReservationController::class, 'bulkDelete'])->name('reservations.bulk-delete');
    });

    // Routes avec paramètre {asset}
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('/assets/{asset}/qrcode', [AssetController::class, 'qrcode'])->name('assets.qrcode');

    Route::middleware('role:admin')->group(function () {
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
        Route::post('/assets/{asset}/generate-qr', [AssetController::class, 'generateQr'])->name('assets.generate-qr');
        Route::patch('/assets/{asset}/toggle-reservable', [AssetController::class, 'toggleReservable'])->name('assets.toggle-reservable');
        Route::patch('/assets/{asset}/toggle-scratch', [AssetController::class, 'toggleScratch'])->name('assets.toggle-scratch');
        Route::patch('/assets/{asset}/toggle-belge', [AssetController::class, 'toggleBelge'])->name('assets.toggle-belge');
        Route::post('/assets/{asset}/duplicate', [AssetController::class, 'duplicate'])->name('assets.duplicate');
        Route::post('/assets/{asset}/link-accessory', [AssetController::class, 'linkAccessory'])->name('assets.link-accessory');
        Route::delete('/assets/{asset}/unlink-accessory', [AssetController::class, 'unlinkAccessory'])->name('assets.unlink-accessory');
        // Fiche publique : description + champs personnalisés
        Route::patch('/assets/{asset}/public-info', [AssetController::class, 'updatePublicInfo'])->name('assets.update-public-info');
        // Fichiers attachés (images / PDFs)
        Route::post('/assets/{asset}/files', [AssetFileController::class, 'store'])->name('assets.files.store');
        Route::patch('/assets/{asset}/files/{file}', [AssetFileController::class, 'update'])->name('assets.files.update');
        Route::delete('/assets/{asset}/files/{file}', [AssetFileController::class, 'destroy'])->name('assets.files.destroy');
    });

    // Categories (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::resource('categories', CategoryController::class);
    });

    // Manufacturers — lecture pour tous, CRUD admin uniquement
    Route::get('/manufacturers', [ManufacturerController::class, 'index'])->name('manufacturers.index');
    Route::middleware('role:admin')->group(function () {
        Route::post('/manufacturers', [ManufacturerController::class, 'store'])->name('manufacturers.store');
        Route::put('/manufacturers/{manufacturer}', [ManufacturerController::class, 'update'])->name('manufacturers.update');
        Route::delete('/manufacturers/{manufacturer}', [ManufacturerController::class, 'destroy'])->name('manufacturers.destroy');
    });

    // Kits — index accessible à tous, gestion admin uniquement
    Route::get('/kits', [KitController::class, 'index'])->name('kits.index');
    Route::middleware('role:admin')->group(function () {
        Route::post('/kits', [KitController::class, 'store'])->name('kits.store');
        Route::post('/kits/missing-item', [KitController::class, 'markMissing'])->name('kits.missing-item');
    });

    // Kits avec paramètre {kit}
    Route::middleware('role:admin')->group(function () {
        Route::put('/kits/{kit}', [KitController::class, 'update'])->name('kits.update');
        Route::post('/kits/{kit}', [KitController::class, 'update']); // Method spoofing pour upload image
        Route::delete('/kits/{kit}', [KitController::class, 'destroy'])->name('kits.destroy');
        Route::post('/kits/{kit}/missing-items/{missingItem}/regularize', [KitController::class, 'regularize'])->name('kits.regularize');
    });

    // Kit reserve — accessible à tous les utilisateurs authentifiés
    Route::post('/kits/{kit}/reserve', [KitController::class, 'reserve'])->name('kits.reserve');

    // Reservations — index for admin only redirect logic is in controller
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('/reservations/{reservation}/add-asset', [ReservationController::class, 'addAsset'])->name('reservations.add-asset');
    Route::delete('/reservations/{reservation}/assets/{asset}', [ReservationController::class, 'removeAsset'])->name('reservations.remove-asset');
    Route::get('/reservations/{reservation}/messages', [ReservationMessageController::class, 'index'])->name('reservations.messages.index');
    Route::post('/reservations/{reservation}/messages', [ReservationMessageController::class, 'store'])->name('reservations.messages.store');

    // Approbation/rejet par le superviseur (vérification auth dans le controller)
    Route::post('/reservations/{reservation}/supervisor-approve', [ReservationController::class, 'supervisorApprove'])->name('reservations.supervisor-approve');
    Route::post('/reservations/{reservation}/supervisor-reject',  [ReservationController::class, 'supervisorReject'])->name('reservations.supervisor-reject');

    // Checkout accessible to reservation owner OR admin (authorization handled in controller)
    Route::post('/reservations/{reservation}/checkout', [ReservationController::class, 'checkout'])->name('reservations.checkout');

    // Espace superviseur — accessible aux superviseurs (professeurs, superviseurs de groupes, admins)
    Route::get('/supervisor/group', [SupervisorController::class, 'group'])->name('supervisor.group');

    Route::middleware('role:admin')->group(function () {
        Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
        Route::post('/reservations/{reservation}/approve', [ReservationController::class, 'approve'])->name('reservations.approve');
        Route::post('/reservations/{reservation}/reject', [ReservationController::class, 'reject'])->name('reservations.reject');
        Route::post('/reservations/{reservation}/return', [ReservationController::class, 'return'])->name('reservations.return');
        Route::post('/reservations/{reservation}/return-asset/{asset}', [ReservationController::class, 'returnAsset'])->name('reservations.return-asset');
        Route::post('/reservations/{reservation}/send-email', [ReservationController::class, 'sendEmail'])->name('reservations.send-email');
        Route::patch('/reservations/{reservation}/dates', [ReservationController::class, 'updateDates'])->name('reservations.update-dates');
        Route::patch('/reservations/{reservation}/admin-update', [ReservationController::class, 'adminUpdate'])->name('reservations.admin-update');
        // Fiche de sortie imprimable
        Route::get('/reservations/{reservation}/print', [ReservationController::class, 'printView'])->name('reservations.print');
        // Ajouter / retirer un equipement d'une reservation
    });

    // Calendar API
    Route::get('/api/reservations/calendar', [ReservationController::class, 'calendarEvents'])->name('reservations.calendar');
    Route::get('/api/events/calendar', [\App\Http\Controllers\EventController::class, 'calendarEvents'])->name('events.calendar');
    Route::get('/api/assets/available', [AssetController::class, 'available'])->name('assets.available');

    // Calendar page (all users)
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // Checkout Forms
    Route::get('/checkout-forms/{form}/fill/{reservation}', [CheckoutFormController::class, 'fill'])->name('checkout-forms.fill');
    Route::post('/checkout-forms/{form}/submit/{reservation}', [CheckoutFormController::class, 'submit'])->name('checkout-forms.submit');

    Route::middleware('role:admin')->group(function () {
        Route::get('/checkout-forms', [CheckoutFormController::class, 'index'])->name('checkout-forms.index');
        Route::get('/checkout-forms/create', [CheckoutFormController::class, 'create'])->name('checkout-forms.create');
        Route::post('/checkout-forms', [CheckoutFormController::class, 'store'])->name('checkout-forms.store');
        Route::get('/checkout-forms/{form}/edit', [CheckoutFormController::class, 'edit'])->name('checkout-forms.edit');
        Route::put('/checkout-forms/{form}', [CheckoutFormController::class, 'update'])->name('checkout-forms.update');
        Route::delete('/checkout-forms/{form}', [CheckoutFormController::class, 'destroy'])->name('checkout-forms.destroy');
    });

    // Import (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/import', [ImportController::class, 'index'])->name('import.index');
        // Équipements (Snipe-IT hardware)
        Route::post('/import/preview', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/process', [ImportController::class, 'process'])->name('import.process');
        // Licences (Snipe-IT licenses)
        Route::post('/import/licenses/preview', [ImportController::class, 'previewLicenses'])->name('import.licenses.preview');
        Route::post('/import/licenses/process', [ImportController::class, 'processLicenses'])->name('import.licenses.process');
        // Fabricants
        Route::post('/import/manufacturers/preview', [ImportController::class, 'previewManufacturers'])->name('import.manufacturers.preview');
        Route::post('/import/manufacturers/process', [ImportController::class, 'processManufacturers'])->name('import.manufacturers.process');
        // Import sauvegarde complète Snipe-IT (ZIP)
        Route::get('/import/snipe-backup', [SnipeItBackupController::class, 'index'])->name('import.snipe-backup');
        Route::post('/import/snipe-backup/analyze', [SnipeItBackupController::class, 'analyze'])->name('import.snipe-backup.analyze');
        Route::post('/import/snipe-backup/import', [SnipeItBackupController::class, 'import'])->name('import.snipe-backup.import');
        Route::post('/import/snipe-backup/cancel', [SnipeItBackupController::class, 'cancel'])->name('import.snipe-backup.cancel');
    });

    // ─── Consommables ────────────────────────────────────────────────────
    // Lecture : tous les utilisateurs connectés
    Route::get('/consommables', [ConsommableController::class, 'index'])->name('consommables.index');

    // Import CSV : routes statiques AVANT les routes avec paramètre {consommable}
    Route::middleware('role:admin')->group(function () {
        Route::post('/consommables/import/preview', [ConsommableController::class, 'importPreview'])->name('consommables.import.preview');
        Route::post('/consommables/import/process', [ConsommableController::class, 'importProcess'])->name('consommables.import.process');
    });

    // CRUD admin + ajustement stock
    Route::middleware('role:admin')->group(function () {
        Route::post('/consommables', [ConsommableController::class, 'store'])->name('consommables.store');
        Route::put('/consommables/{consommable}', [ConsommableController::class, 'update'])->name('consommables.update');
        Route::delete('/consommables/{consommable}', [ConsommableController::class, 'destroy'])->name('consommables.destroy');
        Route::post('/consommables/{consommable}/adjust-stock', [ConsommableController::class, 'adjustStock'])->name('consommables.adjust-stock');
    });

    // Demandes utilisateurs
    Route::post('/consommables/{consommable}/request', [ConsommableRequestController::class, 'store'])->name('consommable-requests.store');
    Route::delete('/consommable-requests/{consommableRequest}', [ConsommableRequestController::class, 'cancel'])->name('consommable-requests.cancel');

    // Traitement demandes (admin)
    Route::middleware('role:admin')->group(function () {
        Route::post('/consommable-requests/{consommableRequest}/approve', [ConsommableRequestController::class, 'approve'])->name('consommable-requests.approve');
        Route::post('/consommable-requests/{consommableRequest}/reject', [ConsommableRequestController::class, 'reject'])->name('consommable-requests.reject');
    });

    // Panier
    Route::get('/cart', function () {
        $requiredForm = \App\Models\CheckoutForm::where('is_active', true)
            ->where('is_required_for_reservation', true)
            ->with('fields')
            ->first();
        $examMode = \App\Models\Setting::get('exam_mode', 'false') === 'true';
        return \Inertia\Inertia::render('Cart/Index', [
            'requiredForm'     => $requiredForm,
            'examMode'         => $examMode,
            'examAdvanceHours' => (int) \App\Models\Setting::get('exam_advance_hours', 24),
            'examProcessStart' => (int) \App\Models\Setting::get('exam_process_start', 7),
            'examProcessEnd'   => (int) \App\Models\Setting::get('exam_process_end', 9),
        ]);
    })->name('cart.index');

    // Scanner
    Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
    Route::post('/scanner/lookup', [ScannerController::class, 'lookup'])->name('scanner.lookup');
    Route::post('/scanner/return', [ScannerController::class, 'returnAsset'])->name('scanner.return');

    // Sessions de scan apairé (smartphone ↔ desktop)
    Route::post('/scan-sessions', [ScanSessionController::class, 'store'])->name('scan-sessions.store');
    Route::get('/scan-sessions/{token}/poll', [ScanSessionController::class, 'poll'])->name('scan-sessions.poll');
    Route::post('/scan-sessions/{token}/close', [ScanSessionController::class, 'close'])->name('scan-sessions.close');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::middleware('role:admin')->group(function () {
        Route::get('/reports/export-csv',             [ReportController::class, 'exportCsv'])->name('reports.export-csv');
        Route::get('/reports/export-csv-licenses',    [ReportController::class, 'exportCsvLicenses'])->name('reports.export-csv-licenses');
        Route::get('/reports/export-csv-computers',   [ReportController::class, 'exportCsvComputers'])->name('reports.export-csv-computers');
        Route::get('/reports/export-csv-consommables',[ReportController::class, 'exportCsvConsommables'])->name('reports.export-csv-consommables');
        Route::get('/reports/export-csv-models',      [ReportController::class, 'exportCsvModels'])->name('reports.export-csv-models');
    });

    // Admin area
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        // Import CSV — AVANT les routes avec {user} pour éviter les conflits
        Route::get('/users/template', [AdminController::class, 'downloadTemplate'])->name('users.import-template');
        Route::post('/users/import/preview', [AdminController::class, 'importPreview'])->name('users.import-preview');
        Route::post('/users/import', [AdminController::class, 'importProcess'])->name('users.import');
        // Routes avec paramètre {user}
        Route::get('/users/{user}', [AdminController::class, 'showUser'])->name('users.show');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/users/{user}/role', [AdminController::class, 'changeRole'])->name('users.role');
        Route::post('/users/{user}/avatar', [AdminController::class, 'updateUserAvatar'])->name('users.avatar');
        // Gestion des rôles & permissions
        Route::get('/roles',  [RolesController::class, 'index'])->name('roles');
        Route::post('/roles', [RolesController::class, 'update'])->name('roles.update');
    });

    // Affectation équipement → équipement (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::post('/asset-assignments', [AssetAssignmentController::class, 'assign'])->name('asset-assignments.assign');
        Route::post('/asset-assignments/{assignment}/return', [AssetAssignmentController::class, 'returnAsset'])->name('asset-assignments.return');
        Route::post('/assets/{asset}/return-all-assignments', [AssetAssignmentController::class, 'returnAll'])->name('asset-assignments.return-all');

        // Événements (admin only)
        Route::get('/events', [\App\Http\Controllers\EventController::class, 'index'])->name('events.index');
        Route::post('/events', [\App\Http\Controllers\EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}', [\App\Http\Controllers\EventController::class, 'show'])->name('events.show');
        Route::put('/events/{event}', [\App\Http\Controllers\EventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [\App\Http\Controllers\EventController::class, 'destroy'])->name('events.destroy');
        Route::post('/events/{event}/reservations', [\App\Http\Controllers\EventController::class, 'createReservation'])->name('events.reservations.create');
    });

    // Waitlist — list d'attente pour les équipements non disponibles
    Route::post('/assets/{asset}/waitlist', [\App\Http\Controllers\WaitlistController::class, 'store'])->name('waitlist.store');
    Route::delete('/assets/{asset}/waitlist', [\App\Http\Controllers\WaitlistController::class, 'destroy'])->name('waitlist.destroy');

    // Maintenance (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/maintenance', [\App\Http\Controllers\MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::post('/maintenance', [\App\Http\Controllers\MaintenanceController::class, 'store'])->name('maintenance.store');
        Route::put('/maintenance/{maintenance}', [\App\Http\Controllers\MaintenanceController::class, 'update'])->name('maintenance.update');
        Route::post('/maintenance/{maintenance}/resolve', [\App\Http\Controllers\MaintenanceController::class, 'resolve'])->name('maintenance.resolve');
        Route::delete('/maintenance/{maintenance}', [\App\Http\Controllers\MaintenanceController::class, 'destroy'])->name('maintenance.destroy');
    });

    // Sauvegardes (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups/create', [BackupController::class, 'create'])->name('backups.create');
        Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::post('/backups/{filename}/restore-existing', [BackupController::class, 'restoreExisting'])->name('backups.restore-existing');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

    // Décheterie (admin only) — routes statiques AVANT {recyclingItem}
    Route::middleware('role:admin')->group(function () {
        Route::get('/recycling', [\App\Http\Controllers\RecyclingController::class, 'index'])->name('recycling.index');
        Route::post('/recycling', [\App\Http\Controllers\RecyclingController::class, 'store'])->name('recycling.store');
        Route::post('/recycling/export-pdf', [\App\Http\Controllers\RecyclingController::class, 'exportPdf'])->name('recycling.export-pdf');
        Route::post('/recycling/export-csv', [\App\Http\Controllers\RecyclingController::class, 'exportCsv'])->name('recycling.export-csv');
        Route::put('/recycling/{recyclingItem}', [\App\Http\Controllers\RecyclingController::class, 'update'])->name('recycling.update');
        Route::delete('/recycling/{recyclingItem}', [\App\Http\Controllers\RecyclingController::class, 'destroy'])->name('recycling.destroy');
        Route::post('/recycling/{recyclingItem}/photo', [\App\Http\Controllers\RecyclingController::class, 'uploadPhoto'])->name('recycling.upload-photo');
    });

    // Licences (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/licenses', [\App\Http\Controllers\LicenseController::class, 'index'])->name('licenses.index');
        Route::post('/licenses', [\App\Http\Controllers\LicenseController::class, 'store'])->name('licenses.store');
        Route::put('/licenses/{license}', [\App\Http\Controllers\LicenseController::class, 'update'])->name('licenses.update');
        Route::delete('/licenses/{license}', [\App\Http\Controllers\LicenseController::class, 'destroy'])->name('licenses.destroy');
        Route::post('/licenses/{license}/assign', [\App\Http\Controllers\LicenseController::class, 'assign'])->name('licenses.assign');
        Route::delete('/licenses/{license}/assignments/{assignment}', [\App\Http\Controllers\LicenseController::class, 'unassign'])->name('licenses.unassign');
    });

    // Postes informatiques (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/computers', [\App\Http\Controllers\ComputerController::class, 'index'])->name('computers.index');
        Route::post('/computers', [\App\Http\Controllers\ComputerController::class, 'store'])->name('computers.store');
        Route::post('/computers/{computer}', [\App\Http\Controllers\ComputerController::class, 'update'])->name('computers.update');
        Route::delete('/computers/{computer}', [\App\Http\Controllers\ComputerController::class, 'destroy'])->name('computers.destroy');
    });

    // Suppliers (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/suppliers', [\App\Http\Controllers\SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [\App\Http\Controllers\SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [\App\Http\Controllers\SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [\App\Http\Controllers\SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // Settings
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
        Route::delete('/settings/logo/{type}', [SettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
    });

    // Inventaire (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/inventory', [\App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/session', [\App\Http\Controllers\InventoryController::class, 'session'])->name('inventory.session');
    });

    // Asset Statuses (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/asset-statuses',                        [AssetStatusController::class, 'index'])->name('asset-statuses.index');
        Route::post('/asset-statuses',                       [AssetStatusController::class, 'store'])->name('asset-statuses.store');
        Route::put('/asset-statuses/{assetStatus}',          [AssetStatusController::class, 'update'])->name('asset-statuses.update');
        Route::delete('/asset-statuses/{assetStatus}',       [AssetStatusController::class, 'destroy'])->name('asset-statuses.destroy');
        Route::post('/asset-statuses/reorder',               [AssetStatusController::class, 'reorder'])->name('asset-statuses.reorder');
    });

    // Equipment Models (admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/models', [App\Http\Controllers\EquipmentModelController::class, 'index'])->name('models.index');
        Route::post('/models', [App\Http\Controllers\EquipmentModelController::class, 'store'])->name('models.store');
        Route::put('/models/{model}', [App\Http\Controllers\EquipmentModelController::class, 'update'])->name('models.update');
        Route::delete('/models/{model}', [App\Http\Controllers\EquipmentModelController::class, 'destroy'])->name('models.destroy');
        Route::post('/models/{model}/duplicate', [App\Http\Controllers\EquipmentModelController::class, 'duplicate'])->name('models.duplicate');
        Route::post('/models/{model}/add-assets', [App\Http\Controllers\EquipmentModelController::class, 'addAssets'])->name('models.add-assets');
        Route::post('/models/{model}/documents', [App\Http\Controllers\EquipmentModelController::class, 'uploadDocument'])->name('models.documents.upload');
        Route::delete('/models/{model}/documents/{document}', [App\Http\Controllers\EquipmentModelController::class, 'deleteDocument'])->name('models.documents.delete');

        // Attribution (scan checkout)
        Route::get('/attribution', [App\Http\Controllers\AttributionController::class, 'index'])->name('attribution.index');
        Route::post('/attribution/scan', [App\Http\Controllers\AttributionController::class, 'scanCheckout'])->name('attribution.scan');
        Route::post('/attribution/scan-multiple', [App\Http\Controllers\AttributionController::class, 'scanMultiple'])->name('attribution.scan-multiple');
        Route::post('/attribution/manual-checkout', [App\Http\Controllers\AttributionController::class, 'manualCheckout'])->name('attribution.manual-checkout');
        Route::post('/attribution/substitute', [App\Http\Controllers\AttributionController::class, 'substituteAndCheckout'])->name('attribution.substitute');
        Route::post('/attribution/add-to-reservation', [App\Http\Controllers\AttributionController::class, 'addExtraAsset'])->name('attribution.add-to-reservation');
        Route::post('/attribution/free-checkout', [App\Http\Controllers\AttributionController::class, 'freeCheckout'])->name('attribution.free-checkout');
        Route::post('/attribution/validate-tag', [App\Http\Controllers\AttributionController::class, 'validateTag'])->name('attribution.validate-tag');
        Route::post('/attribution/free-checkout-bulk', [App\Http\Controllers\AttributionController::class, 'freeBulkCheckout'])->name('attribution.free-checkout-bulk');

        // Returns / Overdue
        Route::get('/returns', [App\Http\Controllers\ReturnController::class, 'index'])->name('returns.index');
        Route::post('/returns/scan', [App\Http\Controllers\ReturnController::class, 'scanReturn'])->name('returns.scan');
        Route::post('/returns/{reservation}/reminder', [App\Http\Controllers\ReturnController::class, 'sendReminder'])->name('returns.reminder');

        // User Groups
        Route::get('/admin/groups', [App\Http\Controllers\UserGroupController::class, 'index'])->name('admin.groups');
        Route::post('/admin/groups', [App\Http\Controllers\UserGroupController::class, 'store'])->name('admin.groups.store');
        Route::put('/admin/groups/{group}', [App\Http\Controllers\UserGroupController::class, 'update'])->name('admin.groups.update');
        Route::delete('/admin/groups/{group}', [App\Http\Controllers\UserGroupController::class, 'destroy'])->name('admin.groups.destroy');
        Route::post('/admin/groups/{group}/members', [App\Http\Controllers\UserGroupController::class, 'addMember'])->name('admin.groups.members.add');
        Route::delete('/admin/groups/{group}/members/{user}', [App\Http\Controllers\UserGroupController::class, 'removeMember'])->name('admin.groups.members.remove');
    });

    // Salles (tous les utilisateurs peuvent voir et reserver)
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('/room-reservations', [RoomController::class, 'storeReservation'])->name('room-reservations.store');
    Route::post('/room-reservations/{roomReservation}/cancel', [RoomController::class, 'cancelReservation'])->name('room-reservations.cancel');
    Route::get('/api/rooms/calendar', [RoomController::class, 'calendarEvents'])->name('rooms.calendar');

    // Salles admin (CRUD + approbation)
    Route::middleware('role:admin')->group(function () {
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
        Route::post('/room-reservations/{roomReservation}/approve', [RoomController::class, 'approveReservation'])->name('room-reservations.approve');
        Route::post('/room-reservations/{roomReservation}/reject', [RoomController::class, 'rejectReservation'])->name('room-reservations.reject');
    });

    // Notifications API (read-all MUST come before {id}/read to avoid param conflict)
    Route::get('/api/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/api/notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/api/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
});

// ── Pages mobiles de scan (sans authentification, accès par token) ──────────
Route::get('/scan/mobile/{token}', [ScanSessionController::class, 'mobileShow'])->name('scan.mobile');
Route::post('/scan/mobile/{token}/submit', [ScanSessionController::class, 'mobileSubmit'])->name('scan.mobile.submit');

require __DIR__.'/auth.php';
