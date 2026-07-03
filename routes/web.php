<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Admin routes — members are redirected away by AdminOnly middleware
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    // Members
    Route::livewire('members', 'pages::members.index')->name('members.index');
    Route::livewire('members/create', 'pages::members.create')->name('members.create');
    Route::livewire('members/{member}/edit', 'pages::members.edit')->name('members.edit');
    Route::livewire('members/{member}', 'pages::members.show')->name('members.show');

    // Payments
    Route::livewire('payments/pending', 'pages::payments.pending')->name('payments.pending');
    Route::livewire('payments/monthly', 'pages::payments.monthly')->name('payments.monthly');
    Route::livewire('payments/registrations', 'pages::payments.registrations')->name('payments.registrations');

    // Membership Plans
    Route::livewire('plans', 'pages::plans.index')->name('plans.index');

    // Gym Settings
    Route::livewire('settings/gym', 'pages::gym.settings')->name('gym.settings');
});

// Member portal — admins are redirected away by MemberOnly middleware
Route::middleware(['auth', 'verified', 'member'])->prefix('portal')->name('portal.')->group(function () {
    Route::livewire('/', 'pages::portal.dashboard')->name('dashboard');
    Route::livewire('/payments', 'pages::portal.payments')->name('payments');
});

require __DIR__.'/settings.php';
