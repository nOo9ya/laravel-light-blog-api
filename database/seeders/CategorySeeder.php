<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 최상위 카테고리들
        $categories = [
            [
                'name' => '프로그래밍',
                'slug' => 'programming',
                'description' => '프로그래밍 관련 포스트',
                'type' => 'post',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => '웹 개발',
                'slug' => 'web-development',
                'description' => '웹 개발 관련 포스트',
                'type' => 'post',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => '모바일',
                'slug' => 'mobile',
                'description' => '모바일 앱 개발',
                'type' => 'post',
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => '데이터베이스',
                'slug' => 'database',
                'description' => '데이터베이스 관련 포스트',
                'type' => 'post',
                'is_active' => true,
                'order' => 4,
            ],
            [
                'name' => '일반 정보',
                'slug' => 'general',
                'description' => '일반 페이지',
                'type' => 'page',
                'is_active' => true,
                'order' => 5,
            ],
        ];

        $createdCategories = [];
        foreach ($categories as $categoryData) {
            $createdCategories[$categoryData['slug']] = Category::create($categoryData);
        }

        // 하위 카테고리들
        $subCategories = [
            // 프로그래밍 하위 카테고리
            [
                'name' => 'PHP',
                'slug' => 'php',
                'description' => 'PHP 프로그래밍',
                'type' => 'post',
                'parent_id' => $createdCategories['programming']->id,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'Python',
                'slug' => 'python',
                'description' => 'Python 프로그래밍',
                'type' => 'post',
                'parent_id' => $createdCategories['programming']->id,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'JavaScript',
                'slug' => 'javascript',
                'description' => 'JavaScript 프로그래밍',
                'type' => 'post',
                'parent_id' => $createdCategories['programming']->id,
                'is_active' => true,
                'order' => 3,
            ],
            // 웹 개발 하위 카테고리
            [
                'name' => 'Laravel',
                'slug' => 'laravel',
                'description' => 'Laravel 프레임워크',
                'type' => 'post',
                'parent_id' => $createdCategories['web-development']->id,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'Vue.js',
                'slug' => 'vuejs',
                'description' => 'Vue.js 프레임워크',
                'type' => 'post',
                'parent_id' => $createdCategories['web-development']->id,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'React',
                'slug' => 'react',
                'description' => 'React 라이브러리',
                'type' => 'post',
                'parent_id' => $createdCategories['web-development']->id,
                'is_active' => true,
                'order' => 3,
            ],
            // 모바일 하위 카테고리
            [
                'name' => 'Android',
                'slug' => 'android',
                'description' => 'Android 앱 개발',
                'type' => 'post',
                'parent_id' => $createdCategories['mobile']->id,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'iOS',
                'slug' => 'ios',
                'description' => 'iOS 앱 개발',
                'type' => 'post',
                'parent_id' => $createdCategories['mobile']->id,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'Flutter',
                'slug' => 'flutter',
                'description' => 'Flutter 크로스 플랫폼 개발',
                'type' => 'post',
                'parent_id' => $createdCategories['mobile']->id,
                'is_active' => true,
                'order' => 3,
            ],
            // 데이터베이스 하위 카테고리
            [
                'name' => 'MySQL',
                'slug' => 'mysql',
                'description' => 'MySQL 데이터베이스',
                'type' => 'post',
                'parent_id' => $createdCategories['database']->id,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'PostgreSQL',
                'slug' => 'postgresql',
                'description' => 'PostgreSQL 데이터베이스',
                'type' => 'post',
                'parent_id' => $createdCategories['database']->id,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'MongoDB',
                'slug' => 'mongodb',
                'description' => 'MongoDB NoSQL 데이터베이스',
                'type' => 'post',
                'parent_id' => $createdCategories['database']->id,
                'is_active' => true,
                'order' => 3,
            ],
            // 일반 정보 하위 카테고리 (페이지용)
            [
                'name' => '회사 소개',
                'slug' => 'about',
                'description' => '회사 및 팀 소개',
                'type' => 'page',
                'parent_id' => $createdCategories['general']->id,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => '서비스',
                'slug' => 'services',
                'description' => '제공 서비스',
                'type' => 'page',
                'parent_id' => $createdCategories['general']->id,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => '연락처',
                'slug' => 'contact',
                'description' => '연락처 정보',
                'type' => 'page',
                'parent_id' => $createdCategories['general']->id,
                'is_active' => true,
                'order' => 3,
            ],
        ];

        foreach ($subCategories as $subCategoryData) {
            Category::create($subCategoryData);
        }

        // 추가 랜덤 카테고리들
        Category::factory()
            ->count(5)
            ->create([
                'type' => 'post',
                'is_active' => true,
            ]);

        // 추가 랜덤 페이지 카테고리들
        Category::factory()
            ->count(3)
            ->create([
                'type' => 'page',
                'is_active' => true,
            ]);
    }
}