<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 기본 태그들
        $tags = [
            // 프로그래밍 언어
            ['name' => 'PHP', 'slug' => 'php', 'description' => 'PHP 프로그래밍 언어', 'color' => '#777BB4'],
            ['name' => 'JavaScript', 'slug' => 'javascript', 'description' => 'JavaScript 프로그래밍 언어', 'color' => '#F7DF1E'],
            ['name' => 'Python', 'slug' => 'python', 'description' => 'Python 프로그래밍 언어', 'color' => '#3776AB'],
            ['name' => 'Java', 'slug' => 'java', 'description' => 'Java 프로그래밍 언어', 'color' => '#007396'],
            ['name' => 'C++', 'slug' => 'cpp', 'description' => 'C++ 프로그래밍 언어', 'color' => '#00599C'],
            ['name' => 'TypeScript', 'slug' => 'typescript', 'description' => 'TypeScript 프로그래밍 언어', 'color' => '#3178C6'],
            
            // 프레임워크
            ['name' => 'Laravel', 'slug' => 'laravel', 'description' => 'Laravel PHP 프레임워크', 'color' => '#FF2D20'],
            ['name' => 'Vue.js', 'slug' => 'vuejs', 'description' => 'Vue.js 프론트엔드 프레임워크', 'color' => '#4FC08D'],
            ['name' => 'React', 'slug' => 'react', 'description' => 'React 라이브러리', 'color' => '#61DAFB'],
            ['name' => 'Angular', 'slug' => 'angular', 'description' => 'Angular 프레임워크', 'color' => '#DD0031'],
            ['name' => 'Django', 'slug' => 'django', 'description' => 'Django Python 프레임워크', 'color' => '#092E20'],
            ['name' => 'Express.js', 'slug' => 'expressjs', 'description' => 'Express.js Node.js 프레임워크', 'color' => '#000000'],
            ['name' => 'Spring', 'slug' => 'spring', 'description' => 'Spring Java 프레임워크', 'color' => '#6DB33F'],
            
            // 데이터베이스
            ['name' => 'MySQL', 'slug' => 'mysql', 'description' => 'MySQL 관계형 데이터베이스', 'color' => '#4479A1'],
            ['name' => 'PostgreSQL', 'slug' => 'postgresql', 'description' => 'PostgreSQL 관계형 데이터베이스', 'color' => '#336791'],
            ['name' => 'MongoDB', 'slug' => 'mongodb', 'description' => 'MongoDB NoSQL 데이터베이스', 'color' => '#47A248'],
            ['name' => 'Redis', 'slug' => 'redis', 'description' => 'Redis 인메모리 데이터베이스', 'color' => '#DC382D'],
            ['name' => 'SQLite', 'slug' => 'sqlite', 'description' => 'SQLite 경량 데이터베이스', 'color' => '#003B57'],
            
            // 도구 및 기술
            ['name' => 'Git', 'slug' => 'git', 'description' => 'Git 버전 관리 시스템', 'color' => '#F05032'],
            ['name' => 'Docker', 'slug' => 'docker', 'description' => 'Docker 컨테이너 기술', 'color' => '#2496ED'],
            ['name' => 'Kubernetes', 'slug' => 'kubernetes', 'description' => 'Kubernetes 컨테이너 오케스트레이션', 'color' => '#326CE5'],
            ['name' => 'AWS', 'slug' => 'aws', 'description' => 'Amazon Web Services', 'color' => '#FF9900'],
            ['name' => 'Azure', 'slug' => 'azure', 'description' => 'Microsoft Azure', 'color' => '#0078D4'],
            ['name' => 'GCP', 'slug' => 'gcp', 'description' => 'Google Cloud Platform', 'color' => '#4285F4'],
            
            // 개발 방법론
            ['name' => 'TDD', 'slug' => 'tdd', 'description' => 'Test Driven Development', 'color' => '#28A745'],
            ['name' => 'Agile', 'slug' => 'agile', 'description' => 'Agile 개발 방법론', 'color' => '#6F42C1'],
            ['name' => 'DevOps', 'slug' => 'devops', 'description' => 'DevOps 문화와 방법론', 'color' => '#326CE5'],
            ['name' => 'CI/CD', 'slug' => 'cicd', 'description' => 'Continuous Integration/Deployment', 'color' => '#FD7E14'],
            
            // 웹 기술
            ['name' => 'HTML', 'slug' => 'html', 'description' => 'HTML 마크업 언어', 'color' => '#E34F26'],
            ['name' => 'CSS', 'slug' => 'css', 'description' => 'CSS 스타일시트', 'color' => '#1572B6'],
            ['name' => 'Sass', 'slug' => 'sass', 'description' => 'Sass CSS 전처리기', 'color' => '#CC6699'],
            ['name' => 'Bootstrap', 'slug' => 'bootstrap', 'description' => 'Bootstrap CSS 프레임워크', 'color' => '#7952B3'],
            ['name' => 'Tailwind CSS', 'slug' => 'tailwindcss', 'description' => 'Tailwind CSS 유틸리티 프레임워크', 'color' => '#06B6D4'],
            
            // 모바일
            ['name' => 'Android', 'slug' => 'android', 'description' => 'Android 모바일 플랫폼', 'color' => '#3DDC84'],
            ['name' => 'iOS', 'slug' => 'ios', 'description' => 'iOS 모바일 플랫폼', 'color' => '#000000'],
            ['name' => 'Flutter', 'slug' => 'flutter', 'description' => 'Flutter 크로스 플랫폼 프레임워크', 'color' => '#02569B'],
            ['name' => 'React Native', 'slug' => 'react-native', 'description' => 'React Native 모바일 프레임워크', 'color' => '#61DAFB'],
            
            // 일반 개념
            ['name' => 'API', 'slug' => 'api', 'description' => 'Application Programming Interface', 'color' => '#FF6B6B'],
            ['name' => 'REST', 'slug' => 'rest', 'description' => 'RESTful API 설계', 'color' => '#4ECDC4'],
            ['name' => 'GraphQL', 'slug' => 'graphql', 'description' => 'GraphQL 쿼리 언어', 'color' => '#E10098'],
            ['name' => 'Microservices', 'slug' => 'microservices', 'description' => '마이크로서비스 아키텍처', 'color' => '#45B7D1'],
            ['name' => 'Security', 'slug' => 'security', 'description' => '보안 관련 내용', 'color' => '#DC3545'],
            ['name' => 'Performance', 'slug' => 'performance', 'description' => '성능 최적화', 'color' => '#FFC107'],
            
            // 튜토리얼 및 팁
            ['name' => 'Tutorial', 'slug' => 'tutorial', 'description' => '튜토리얼 가이드', 'color' => '#17A2B8'],
            ['name' => 'Tips', 'slug' => 'tips', 'description' => '개발 팁', 'color' => '#20C997'],
            ['name' => 'Best Practices', 'slug' => 'best-practices', 'description' => '모범 사례', 'color' => '#6610F2'],
            ['name' => 'Troubleshooting', 'slug' => 'troubleshooting', 'description' => '문제 해결', 'color' => '#E83E8C'],
        ];

        foreach ($tags as $tagData) {
            Tag::create($tagData);
        }

        // 추가 랜덤 태그들
        Tag::factory()
            ->count(15)
            ->create();
    }
}