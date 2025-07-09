<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use App\Models\Tag;
use App\Models\SeoMeta;
use App\Models\PostAttachment;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 사용자, 카테고리, 태그 데이터 가져오기
        $users = User::where('role', '!=', 'user')->get();
        $categories = Category::where('type', 'post')->get();
        $tags = Tag::all();

        if ($users->isEmpty() || $categories->isEmpty()) {
            $this->command->warn('사용자나 카테고리가 없습니다. UserSeeder와 CategorySeeder를 먼저 실행해주세요.');
            return;
        }

        // 샘플 포스트 데이터
        $samplePosts = [
            [
                'title' => 'Laravel 11 새로운 기능 완전 가이드',
                'content' => $this->getLaravelContent(),
                'summary' => 'Laravel 11에서 새롭게 추가된 기능들과 변경사항을 자세히 알아보겠습니다.',
                'status' => 'published',
                'tags' => ['laravel', 'php', 'tutorial'],
            ],
            [
                'title' => 'Vue.js 3 Composition API 마스터하기',
                'content' => $this->getVueContent(),
                'summary' => 'Vue.js 3의 Composition API를 통해 더욱 강력한 컴포넌트를 만드는 방법을 알아보겠습니다.',
                'status' => 'published',
                'tags' => ['vuejs', 'javascript', 'tutorial'],
            ],
            [
                'title' => 'Docker를 활용한 Laravel 개발 환경 구축',
                'content' => $this->getDockerContent(),
                'summary' => 'Docker를 사용하여 Laravel 프로젝트의 개발 환경을 구축하는 완전한 가이드입니다.',
                'status' => 'published',
                'tags' => ['docker', 'laravel', 'devops'],
            ],
            [
                'title' => 'MySQL 성능 최적화 실전 가이드',
                'content' => $this->getMySQLContent(),
                'summary' => 'MySQL 데이터베이스의 성능을 극대화하는 다양한 최적화 기법들을 실제 사례와 함께 알아보겠습니다.',
                'status' => 'published',
                'tags' => ['mysql', 'database', 'performance'],
            ],
            [
                'title' => 'React Hooks 완전 정복',
                'content' => $this->getReactContent(),
                'summary' => 'React Hooks의 모든 것을 예제와 함께 배워보겠습니다.',
                'status' => 'published',
                'tags' => ['react', 'javascript', 'tutorial'],
            ],
            [
                'title' => 'TDD로 Laravel API 개발하기',
                'content' => $this->getTDDContent(),
                'summary' => '테스트 주도 개발 방법론을 적용하여 견고한 Laravel API를 개발하는 방법을 알아보겠습니다.',
                'status' => 'published',
                'tags' => ['tdd', 'laravel', 'api', 'best-practices'],
            ],
            [
                'title' => 'AWS를 활용한 Laravel 애플리케이션 배포',
                'content' => $this->getAWSContent(),
                'summary' => 'Amazon Web Services를 사용하여 Laravel 애플리케이션을 안전하고 확장 가능하게 배포하는 방법입니다.',
                'status' => 'published',
                'tags' => ['aws', 'laravel', 'deployment', 'devops'],
            ],
            [
                'title' => 'JavaScript ES2024 새로운 기능들',
                'content' => $this->getES2024Content(),
                'summary' => 'JavaScript ES2024에서 새롭게 추가된 기능들과 사용법을 예제와 함께 알아보겠습니다.',
                'status' => 'draft',
                'tags' => ['javascript', 'es2024', 'tutorial'],
            ],
        ];

        $createdPosts = [];

        // 샘플 포스트 생성
        foreach ($samplePosts as $index => $postData) {
            $category = $categories->random();
            $user = $users->random();

            $post = Post::create([
                'title' => $postData['title'],
                'slug' => \Str::slug($postData['title']),
                'content' => $postData['content'],
                'summary' => $postData['summary'],
                'category_id' => $category->id,
                'user_id' => $user->id,
                'status' => $postData['status'],
                'published_at' => $postData['status'] === 'published' ? now()->subDays(rand(1, 30)) : null,
                'views_count' => rand(10, 1000),
            ]);

            // 태그 연결
            if (!empty($postData['tags'])) {
                $postTags = Tag::whereIn('slug', $postData['tags'])->get();
                if ($postTags->isNotEmpty()) {
                    $post->tags()->attach($postTags->pluck('id'));
                }
            }

            // SEO 메타 데이터 생성
            SeoMeta::factory()->complete()->create([
                'post_id' => $post->id,
                'meta_title' => $post->title,
                'meta_description' => $post->summary,
                'og_title' => $post->title,
                'og_description' => $post->summary,
                'twitter_title' => $post->title,
                'twitter_description' => $post->summary,
            ]);

            // 일부 포스트에 첨부파일 추가
            if ($index % 3 === 0) {
                PostAttachment::factory()
                    ->count(rand(1, 3))
                    ->create(['post_id' => $post->id]);
            }

            $createdPosts[] = $post;
        }

        // 추가 랜덤 포스트들 생성
        $additionalPosts = Post::factory()
            ->count(25)
            ->create([
                'user_id' => fn() => $users->random()->id,
                'category_id' => fn() => $categories->random()->id,
                'status' => fn() => fake()->randomElement(['published', 'published', 'published', 'draft']), // 75% 확률로 published
                'published_at' => fn() => fake()->dateTimeBetween('-6 months', 'now'),
                'views_count' => fn() => fake()->numberBetween(0, 500),
            ]);

        // 추가 포스트들에 태그 연결
        foreach ($additionalPosts as $post) {
            $randomTags = $tags->random(rand(2, 5));
            $post->tags()->attach($randomTags->pluck('id'));

            // SEO 메타 데이터 생성 (70% 확률)
            if (fake()->boolean(70)) {
                SeoMeta::factory()->create(['post_id' => $post->id]);
            }

            // 첨부파일 생성 (30% 확률)
            if (fake()->boolean(30)) {
                PostAttachment::factory()
                    ->count(rand(1, 2))
                    ->create(['post_id' => $post->id]);
            }
        }

        $this->command->info('포스트 시더 완료: ' . (count($samplePosts) + 25) . '개의 포스트가 생성되었습니다.');
    }

    private function getLaravelContent(): string
    {
        return "
# Laravel 11 새로운 기능 완전 가이드

Laravel 11이 출시되면서 많은 새로운 기능들이 추가되었습니다. 이번 글에서는 주요 변경사항들을 자세히 알아보겠습니다.

## 주요 새 기능들

### 1. 향상된 Route Model Binding

Laravel 11에서는 Route Model Binding이 더욱 강력해졌습니다.

```php
Route::get('/posts/{post:slug}', [PostController::class, 'show']);
```

### 2. 새로운 Eloquent 기능들

Eloquent ORM에도 많은 개선사항이 있습니다.

```php
// 새로운 upsert 메서드
Post::upsert([
    ['title' => 'Post 1', 'content' => 'Content 1'],
    ['title' => 'Post 2', 'content' => 'Content 2'],
], ['title'], ['content']);
```

### 3. 개선된 Validation

폼 검증 기능도 더욱 강력해졌습니다.

```php
$request->validate([
    'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
]);
```

## 성능 개선사항

Laravel 11에서는 전반적인 성능이 크게 개선되었습니다:

- 쿼리 성능 20% 향상
- 메모리 사용량 15% 감소
- 캐시 효율성 개선

## 마무리

Laravel 11은 개발자 경험과 성능 모두를 크게 개선한 버전입니다. 새로운 기능들을 적극 활용해보시기 바랍니다.
        ";
    }

    private function getVueContent(): string
    {
        return "
# Vue.js 3 Composition API 마스터하기

Vue.js 3의 Composition API는 컴포넌트 로직을 더욱 유연하게 구성할 수 있게 해줍니다.

## Composition API란?

Composition API는 Vue 3에서 도입된 새로운 방식으로, 함수 기반으로 컴포넌트 로직을 작성할 수 있습니다.

```javascript
import { ref, computed, onMounted } from 'vue'

export default {
  setup() {
    const count = ref(0)
    const doubleCount = computed(() => count.value * 2)
    
    const increment = () => {
      count.value++
    }
    
    onMounted(() => {
      console.log('Component mounted!')
    })
    
    return {
      count,
      doubleCount,
      increment
    }
  }
}
```

## 주요 Composables

### 1. ref와 reactive

```javascript
import { ref, reactive } from 'vue'

const count = ref(0)
const state = reactive({
  name: 'Vue.js',
  version: 3
})
```

### 2. computed

```javascript
const doubleCount = computed(() => count.value * 2)
```

### 3. watch와 watchEffect

```javascript
watch(count, (newVal, oldVal) => {
  console.log(`Count changed from ${oldVal} to ${newVal}`)
})

watchEffect(() => {
  console.log(`Count is ${count.value}`)
})
```

## 커스텀 Composables

재사용 가능한 로직을 만들 수 있습니다:

```javascript
function useCounter() {
  const count = ref(0)
  
  const increment = () => count.value++
  const decrement = () => count.value--
  
  return { count, increment, decrement }
}
```

Composition API를 마스터하면 더욱 maintainable한 Vue.js 애플리케이션을 만들 수 있습니다.
        ";
    }

    private function getDockerContent(): string
    {
        return "
# Docker를 활용한 Laravel 개발 환경 구축

Docker를 사용하면 일관된 개발 환경을 쉽게 구축할 수 있습니다.

## Dockerfile 작성

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \\
    git \\
    curl \\
    libpng-dev \\
    libonig-dev \\
    libxml2-dev \\
    zip \\
    unzip

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . /var/www

RUN composer install
```

## docker-compose.yml 설정

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: laravel-app
    container_name: laravel-app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./:/var/www
    networks:
      - laravel

  webserver:
    image: nginx:alpine
    container_name: laravel-webserver
    restart: unless-stopped
    ports:
      - \"8000:80\"
    volumes:
      - ./:/var/www
      - ./nginx/conf.d/:/etc/nginx/conf.d/
    networks:
      - laravel

  db:
    image: mysql:8.0
    container_name: laravel-db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: laravel
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_PASSWORD: secret
      MYSQL_USER: laravel
    volumes:
      - dbdata:/var/lib/mysql
    networks:
      - laravel

networks:
  laravel:
    driver: bridge

volumes:
  dbdata:
    driver: local
```

## 개발 워크플로우

1. 컨테이너 시작: `docker-compose up -d`
2. 의존성 설치: `docker-compose exec app composer install`
3. 마이그레이션 실행: `docker-compose exec app php artisan migrate`

Docker를 사용하면 팀원 모두가 동일한 환경에서 개발할 수 있습니다.
        ";
    }

    private function getMySQLContent(): string
    {
        return "
# MySQL 성능 최적화 실전 가이드

MySQL 데이터베이스의 성능을 극대화하는 방법들을 알아보겠습니다.

## 인덱스 최적화

### 1. 적절한 인덱스 생성

```sql
-- 복합 인덱스 생성
CREATE INDEX idx_user_status_created ON posts (user_id, status, created_at);

-- 부분 인덱스 활용
CREATE INDEX idx_title_prefix ON posts (title(20));
```

### 2. 인덱스 사용 분석

```sql
EXPLAIN SELECT * FROM posts WHERE user_id = 1 AND status = 'published';
```

## 쿼리 최적화

### 1. N+1 문제 해결

```php
// 잘못된 예
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user->name; // N+1 쿼리 발생
}

// 올바른 예
$posts = Post::with('user')->get();
foreach ($posts as $post) {
    echo $post->user->name; // 단일 쿼리로 해결
}
```

### 2. 페이지네이션 최적화

```sql
-- OFFSET 대신 커서 기반 페이지네이션
SELECT * FROM posts WHERE id > 1000 ORDER BY id LIMIT 20;
```

## 설정 최적화

### my.cnf 설정

```ini
[mysqld]
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
query_cache_size = 128M
tmp_table_size = 64M
max_heap_table_size = 64M
```

## 모니터링

### 1. 슬로우 쿼리 로그

```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;
```

### 2. 성능 스키마 활용

```sql
SELECT * FROM performance_schema.events_statements_summary_by_digest
ORDER BY sum_timer_wait DESC LIMIT 10;
```

체계적인 성능 최적화로 MySQL의 성능을 극대화할 수 있습니다.
        ";
    }

    private function getReactContent(): string
    {
        return "
# React Hooks 완전 정복

React Hooks를 마스터하여 더욱 강력한 컴포넌트를 만들어보겠습니다.

## 기본 Hooks

### useState

```jsx
import React, { useState } from 'react';

function Counter() {
  const [count, setCount] = useState(0);
  
  return (
    <div>
      <p>Count: {count}</p>
      <button onClick={() => setCount(count + 1)}>
        Increment
      </button>
    </div>
  );
}
```

### useEffect

```jsx
import React, { useState, useEffect } from 'react';

function UserProfile({ userId }) {
  const [user, setUser] = useState(null);
  
  useEffect(() => {
    fetchUser(userId).then(setUser);
  }, [userId]);
  
  if (!user) return <div>Loading...</div>;
  
  return <div>{user.name}</div>;
}
```

## 고급 Hooks

### useContext

```jsx
import React, { createContext, useContext } from 'react';

const ThemeContext = createContext();

function ThemeProvider({ children }) {
  const [theme, setTheme] = useState('light');
  
  return (
    <ThemeContext.Provider value={{ theme, setTheme }}>
      {children}
    </ThemeContext.Provider>
  );
}

function ThemedButton() {
  const { theme, setTheme } = useContext(ThemeContext);
  
  return (
    <button 
      style={{ background: theme === 'light' ? '#fff' : '#333' }}
      onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
    >
      Toggle Theme
    </button>
  );
}
```

### useReducer

```jsx
import React, { useReducer } from 'react';

const initialState = { count: 0 };

function reducer(state, action) {
  switch (action.type) {
    case 'increment':
      return { count: state.count + 1 };
    case 'decrement':
      return { count: state.count - 1 };
    default:
      return state;
  }
}

function Counter() {
  const [state, dispatch] = useReducer(reducer, initialState);
  
  return (
    <div>
      Count: {state.count}
      <button onClick={() => dispatch({ type: 'increment' })}>+</button>
      <button onClick={() => dispatch({ type: 'decrement' })}>-</button>
    </div>
  );
}
```

## 커스텀 Hooks

```jsx
function useLocalStorage(key, initialValue) {
  const [storedValue, setStoredValue] = useState(() => {
    try {
      const item = window.localStorage.getItem(key);
      return item ? JSON.parse(item) : initialValue;
    } catch (error) {
      return initialValue;
    }
  });
  
  const setValue = (value) => {
    try {
      setStoredValue(value);
      window.localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      console.error(error);
    }
  };
  
  return [storedValue, setValue];
}
```

React Hooks를 활용하면 더욱 재사용 가능하고 테스트하기 쉬운 컴포넌트를 만들 수 있습니다.
        ";
    }

    private function getTDDContent(): string
    {
        return "
# TDD로 Laravel API 개발하기

테스트 주도 개발(TDD)을 통해 견고한 Laravel API를 개발하는 방법을 알아보겠습니다.

## TDD 사이클

1. **Red**: 실패하는 테스트 작성
2. **Green**: 테스트를 통과시키는 최소한의 코드 작성
3. **Refactor**: 코드 개선

## 실제 예제: 포스트 API

### 1. 테스트 작성 (Red)

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function it_can_create_a_post()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'api')
            ->postJson('/api/posts', [
                'title' => 'Test Post',
                'content' => 'This is a test post content.',
            ]);
        
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'Test Post',
                    'content' => 'This is a test post content.',
                ]
            ]);
        
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'user_id' => $user->id,
        ]);
    }
}
```

### 2. 구현 (Green)

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use App\Http\Resources\PostResource;

class PostController extends Controller
{
    public function store(PostRequest $request)
    {
        $post = Post::create([
            'title' => $request->title,
            'content' => $request->content,
            'user_id' => auth()->id(),
        ]);
        
        return new PostResource($post);
    }
}
```

### 3. Request Validation

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ];
    }
}
```

## 고급 테스트 패턴

### Factory 활용

```php
// PostFactory.php
public function definition()
{
    return [
        'title' => $this->faker->sentence,
        'content' => $this->faker->paragraphs(3, true),
        'user_id' => User::factory(),
    ];
}

// 테스트에서 사용
$posts = Post::factory()->count(5)->create();
```

### Mock 활용

```php
public function test_it_sends_notification_when_post_created()
{
    Notification::fake();
    
    $user = User::factory()->create();
    
    $this->actingAs($user, 'api')
        ->postJson('/api/posts', [
            'title' => 'Test Post',
            'content' => 'Content',
        ]);
    
    Notification::assertSentTo($user, PostCreatedNotification::class);
}
```

## 테스트 구조 최적화

### Base Test Class

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;
    
    protected function authenticatedUser($attributes = [])
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user, 'api');
        
        return $user;
    }
}
```

TDD를 통해 더욱 안정적이고 maintainable한 API를 개발할 수 있습니다.
        ";
    }

    private function getAWSContent(): string
    {
        return "
# AWS를 활용한 Laravel 애플리케이션 배포

Amazon Web Services를 사용하여 Laravel 애플리케이션을 배포하는 완전한 가이드입니다.

## 아키텍처 설계

### 기본 구성요소

- **EC2**: 웹 서버 호스팅
- **RDS**: MySQL 데이터베이스
- **S3**: 정적 파일 저장
- **CloudFront**: CDN
- **Route 53**: DNS 관리

## EC2 인스턴스 설정

### 1. 인스턴스 생성

```bash
# Ubuntu 22.04 LTS 선택
# t3.medium 이상 권장
# 보안 그룹: HTTP(80), HTTPS(443), SSH(22)
```

### 2. 서버 환경 구성

```bash
# 시스템 업데이트
sudo apt update && sudo apt upgrade -y

# PHP 8.3 설치
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.3 php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-gd

# Nginx 설치
sudo apt install nginx

# Composer 설치
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3. Nginx 설정

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/laravel/public;
    
    add_header X-Frame-Options \"SAMEORIGIN\";
    add_header X-Content-Type-Options \"nosniff\";
    
    index index.php;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \\.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
```

## RDS 데이터베이스 설정

### 1. RDS 인스턴스 생성

```bash
# MySQL 8.0 선택
# db.t3.micro (개발용) 또는 db.t3.small (운영용)
# Multi-AZ 배포 권장 (고가용성)
```

### 2. Laravel 환경 설정

```env
DB_CONNECTION=mysql
DB_HOST=your-rds-endpoint.amazonaws.com
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=admin
DB_PASSWORD=your-secure-password
```

## S3 파일 저장소 설정

### 1. S3 버킷 생성

```bash
# Laravel 애플리케이션용 버킷 생성
# 공개 읽기 권한 설정 (필요시)
```

### 2. Laravel S3 설정

```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
],
```

## 배포 자동화

### GitHub Actions 설정

```yaml
name: Deploy to AWS

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.2
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.KEY }}
        script: |
          cd /var/www/laravel
          git pull origin main
          composer install --no-dev --optimize-autoloader
          php artisan migrate --force
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
          sudo systemctl reload nginx
```

## 보안 설정

### SSL 인증서 (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

### 방화벽 설정

```bash
sudo ufw enable
sudo ufw allow 22    # SSH
sudo ufw allow 80    # HTTP
sudo ufw allow 443   # HTTPS
```

AWS를 활용하면 확장 가능하고 안정적인 Laravel 애플리케이션을 배포할 수 있습니다.
        ";
    }

    private function getES2024Content(): string
    {
        return "
# JavaScript ES2024 새로운 기능들

JavaScript ES2024(ES15)에서 새롭게 추가된 기능들을 알아보겠습니다.

## 주요 새 기능들

### 1. Array.prototype.toSorted()

기존 배열을 변경하지 않고 정렬된 새 배열을 반환합니다.

```javascript
const numbers = [3, 1, 4, 1, 5];
const sorted = numbers.toSorted(); // [1, 1, 3, 4, 5]
console.log(numbers); // [3, 1, 4, 1, 5] (원본 유지)
```

### 2. Array.prototype.toReversed()

```javascript
const arr = [1, 2, 3, 4, 5];
const reversed = arr.toReversed(); // [5, 4, 3, 2, 1]
console.log(arr); // [1, 2, 3, 4, 5] (원본 유지)
```

### 3. Array.prototype.with()

특정 인덱스의 값을 변경한 새 배열을 반환합니다.

```javascript
const arr = ['a', 'b', 'c'];
const newArr = arr.with(1, 'x'); // ['a', 'x', 'c']
console.log(arr); // ['a', 'b', 'c'] (원본 유지)
```

### 4. Array.prototype.toSpliced()

```javascript
const arr = [1, 2, 3, 4, 5];
const spliced = arr.toSpliced(1, 2, 'a', 'b'); // [1, 'a', 'b', 4, 5]
console.log(arr); // [1, 2, 3, 4, 5] (원본 유지)
```

## Object.groupBy()

배열을 객체로 그룹화합니다.

```javascript
const people = [
  { name: 'Alice', age: 25 },
  { name: 'Bob', age: 30 },
  { name: 'Charlie', age: 25 }
];

const grouped = Object.groupBy(people, person => person.age);
/*
{
  25: [{ name: 'Alice', age: 25 }, { name: 'Charlie', age: 25 }],
  30: [{ name: 'Bob', age: 30 }]
}
*/
```

## Map.groupBy()

Map 객체로 그룹화합니다.

```javascript
const inventory = [
  { name: 'asparagus', type: 'vegetables', quantity: 5 },
  { name: 'bananas', type: 'fruit', quantity: 0 },
  { name: 'goat', type: 'meat', quantity: 23 },
];

const result = Map.groupBy(inventory, ({ type }) => type);
// Map { 'vegetables' => [...], 'fruit' => [...], 'meat' => [...] }
```

## Promise.withResolvers()

Promise를 외부에서 제어할 수 있게 해줍니다.

```javascript
const { promise, resolve, reject } = Promise.withResolvers();

// 어디서든 promise를 resolve/reject할 수 있음
setTimeout(() => resolve('Success!'), 1000);

promise.then(console.log); // 'Success!'
```

## Temporal API (제안 단계)

날짜와 시간을 더 정확하게 다룰 수 있는 새로운 API입니다.

```javascript
// 현재 제안 단계, 브라우저 지원 제한적
const now = Temporal.Now.plainDateTimeISO();
const date = Temporal.PlainDate.from('2024-01-01');
const time = Temporal.PlainTime.from('14:30:00');

const dateTime = date.toPlainDateTime(time);
console.log(dateTime.toString()); // '2024-01-01T14:30:00'
```

## 실용적인 활용 예제

### 불변성을 유지하는 상태 관리

```javascript
class TodoStore {
  constructor() {
    this.todos = [];
  }
  
  addTodo(todo) {
    this.todos = this.todos.with(this.todos.length, todo);
  }
  
  removeTodo(index) {
    this.todos = this.todos.toSpliced(index, 1);
  }
  
  sortTodos() {
    this.todos = this.todos.toSorted((a, b) => 
      a.priority - b.priority
    );
  }
}
```

### 데이터 그룹화 활용

```javascript
function analyzeUserData(users) {
  // 연령대별 그룹화
  const ageGroups = Object.groupBy(users, user => {
    return Math.floor(user.age / 10) * 10;
  });
  
  // 각 그룹의 통계 계산
  const stats = Object.entries(ageGroups).map(([ageGroup, users]) => ({
    ageGroup: `${ageGroup}s`,
    count: users.length,
    averageAge: users.reduce((sum, user) => sum + user.age, 0) / users.length
  }));
  
  return stats.toSorted((a, b) => parseInt(a.ageGroup) - parseInt(b.ageGroup));
}
```

ES2024의 새로운 기능들을 활용하면 더욱 함수형이고 안전한 JavaScript 코드를 작성할 수 있습니다.
        ";
    }
}