<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'school_name', 'value' => 'Lycée', 'group' => 'general'],
            ['key' => 'school_address', 'value' => '', 'group' => 'general'],
            ['key' => 'school_phone', 'value' => '', 'group' => 'general'],
            ['key' => 'logo', 'value' => null, 'group' => 'general'],

            // Visibility
            ['key' => 'filter_by_reservable', 'value' => 'true', 'group' => 'visibility'],

            // Tabs
            ['key' => 'tab_categories',    'value' => 'true', 'group' => 'tabs'],
            ['key' => 'tab_locations',     'value' => 'true', 'group' => 'tabs'],
            ['key' => 'tab_manufacturers', 'value' => 'true', 'group' => 'tabs'],

            // Reservations
            ['key' => 'max_reservation_days', 'value' => '30', 'group' => 'reservations'],
            ['key' => 'max_assets_per_reservation', 'value' => '10', 'group' => 'reservations'],
            ['key' => 'allow_overlap_pending', 'value' => 'false', 'group' => 'reservations'],
            ['key' => 'auto_approve', 'value' => 'false', 'group' => 'reservations'],

            // Emails
            ['key' => 'notify_admin_new_reservation', 'value' => 'true', 'group' => 'emails'],
            ['key' => 'notify_user_approved', 'value' => 'true', 'group' => 'emails'],
            ['key' => 'send_checkout_summary', 'value' => 'true', 'group' => 'emails'],
        ];

        foreach ($settings as $setting) {
            \App\Models\Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        $this->command->info('Settings initialized');
    }
}
