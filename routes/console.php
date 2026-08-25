<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Rappels de retour — scrute les réservations actives toutes les minutes
Schedule::command('app:send-return-reminders')
    ->everyMinute()
    ->withoutOverlapping();

// Annulation automatique — scrute les réservations approuvées non retirées toutes les heures
Schedule::command('app:cancel-expired-reservations')
    ->hourly()
    ->withoutOverlapping();

// Alertes stock bas consommables — une fois par jour à 8h
Schedule::command('app:check-low-stock')
    ->dailyAt('08:00')
    ->withoutOverlapping();

// Rappels de tâches To-Do — toutes les minutes
Schedule::command('app:check-todo-reminders')
    ->everyMinute()
    ->withoutOverlapping();

// Remise automatique de préparations — toutes les minutes
Schedule::command('app:auto-checkout-preparations')
    ->everyMinute()
    ->withoutOverlapping();
