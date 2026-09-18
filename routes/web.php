<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Ai\AnahiAIController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Extractions\ExtractionController;
use App\Http\Controllers\FiscalDocuments\FiscalDocumentController;
use App\Http\Controllers\Sri\SriCredentialController;
use App\Http\Controllers\Webhooks\WebhookEndpointController;
use App\Http\Controllers\Companies\CompanyController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('ai', [AnahiAIController::class, 'index'])->name('ai.index');
        Route::post('ai/chat', [AnahiAIController::class, 'chat'])->name('ai.chat');
        // Extractions
        Route::prefix('extractions')->name('extractions.')->group(function () {
            Route::get('/', [ExtractionController::class, 'index'])->name('index');
            Route::post('/', [ExtractionController::class, 'store'])->name('store');
            Route::prefix('{extraction}')->group(function () {
                Route::get('/', [ExtractionController::class, 'show'])->name('show');
                Route::post('retry', [ExtractionController::class, 'retry'])->name('retry');
                Route::post('cancel', [ExtractionController::class, 'cancel'])->name('cancel');
            });
        });
        // Company
        Route::prefix('company')->name('companies.')->group(function () {
            Route::get('/', [CompanyController::class, 'edit'])->name('edit');
            Route::patch('/', [CompanyController::class, 'update'])->name('update');
        });

        // Documents
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [FiscalDocumentController::class, 'index'])->name('index');
            Route::prefix('{document}')->group(function () {
                Route::get('/', [FiscalDocumentController::class, 'show'])->name('show');
                Route::prefix('download')->name('download.')->group(function () {
                    Route::get('xml', [FiscalDocumentController::class, 'downloadXml'])->name('xml');
                    Route::get('pdf', [FiscalDocumentController::class, 'downloadPdf'])->name('pdf');
                    Route::get('json', [FiscalDocumentController::class, 'downloadJson'])->name('json');
                    Route::get('zip', [FiscalDocumentController::class, 'downloadZip'])->name('zip');
                });
            });
        });

        // Webhooks
        Route::prefix('webhooks')->name('webhooks.')->group(function () {
            Route::get('/', [WebhookEndpointController::class, 'index'])->name('index');
            Route::post('/', [WebhookEndpointController::class, 'store'])->name('store');
            Route::put('{webhook}', [WebhookEndpointController::class, 'update'])->name('update');
            Route::delete('{webhook}', [WebhookEndpointController::class, 'destroy'])->name('destroy');
        });

        // SRI Credentials
        Route::prefix('sri-credentials')->name('sri-credentials.')->group(function () {
            Route::get('/', [SriCredentialController::class, 'edit'])->name('edit');
            Route::put('/', [SriCredentialController::class, 'update'])->name('update');
            Route::post('test', [SriCredentialController::class, 'testConnection'])->name('test');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
