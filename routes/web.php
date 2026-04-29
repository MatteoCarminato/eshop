<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/dashboard', function () {
    return view('welcome');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';


Route::resource('groups', \App\Http\Controllers\GroupController::class);
Route::post('groups/{group}/add-clients', [\App\Http\Controllers\GroupController::class, 'addClients'])->name('groups.addClients');

Route::resource('clients', \App\Http\Controllers\ClientController::class);
Route::post('clients/add-to-groups', [\App\Http\Controllers\ClientController::class, 'addToGroups'])->name('clients.addToGroups');




Route::fallback(function () {
    return view('welcome');
});
