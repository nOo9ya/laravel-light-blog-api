<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'site_name',
                'value' => 'Laravel Light Blog',
                'type' => 'string',
                'description' => '웹사이트 이름',
            ],
            [
                'key' => 'site_description',
                'value' => '경량화된 고성능 웹진 플랫폼',
                'type' => 'string',
                'description' => '웹사이트 설명',
            ],
            [
                'key' => 'site_theme',
                'value' => 'default',
                'type' => 'string',
                'description' => '현재 활성화된 웹사이트 테마',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
