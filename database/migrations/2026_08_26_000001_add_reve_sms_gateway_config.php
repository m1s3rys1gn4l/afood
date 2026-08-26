<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Setting::where('settings_type', 'sms_config')->where('key_name', 'reve_sms')->exists()) {
            return;
        }

        $values = [
            'gateway' => 'reve_sms',
            'mode' => 'live',
            'status' => 0,
            'api_key' => '',
            'secret_key' => '',
            'sender_id' => '',
            'otp_template' => '',
        ];

        Setting::create([
            'key_name' => 'reve_sms',
            'live_values' => $values,
            'test_values' => $values,
            'settings_type' => 'sms_config',
            'mode' => 'live',
            'is_active' => 0,
        ]);
    }

    public function down(): void
    {
        Setting::where('settings_type', 'sms_config')->where('key_name', 'reve_sms')->delete();
    }
};
