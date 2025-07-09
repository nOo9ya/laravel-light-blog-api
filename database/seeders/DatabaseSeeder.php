<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 기본 설정 시더 먼저 실행
        $this->call(SettingsSeeder::class);
        
        // 사용자 생성
        $this->call(UserSeeder::class);
        
        // 카테고리 생성
        $this->call(CategorySeeder::class);
        
        // 태그 생성
        $this->call(TagSeeder::class);
        
        // 포스트 생성 (SEO 메타 및 첨부파일 포함)
        $this->call(PostSeeder::class);
        
        // 페이지 생성
        $this->call(PageSeeder::class);
        
        // 댓글 생성 (포스트 생성 후)
        $this->call(CommentSeeder::class);
    }
}
