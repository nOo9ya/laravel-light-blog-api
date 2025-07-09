<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 관리자 계정 생성
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // 작성자 계정 생성
        User::create([
            'name' => 'Author',
            'email' => 'author@example.com',
            'password' => Hash::make('password'),
            'role' => 'author',
            'email_verified_at' => now(),
        ]);

        // 일반 사용자 계정 생성
        User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        // 추가 테스트 사용자들 생성
        User::factory()
            ->count(10)
            ->create([
                'role' => 'user',
                'email_verified_at' => now(),
            ]);

        // 추가 작성자들 생성
        User::factory()
            ->count(3)
            ->create([
                'role' => 'author',
                'email_verified_at' => now(),
            ]);
    }
}