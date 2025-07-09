<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posts = Post::where('status', 'published')->get();
        $users = User::all();

        if ($posts->isEmpty()) {
            $this->command->warn('발행된 포스트가 없습니다. PostSeeder를 먼저 실행해주세요.');
            return;
        }

        foreach ($posts as $post) {
            // 각 포스트마다 0-8개의 댓글 생성
            $commentCount = fake()->numberBetween(0, 8);
            
            for ($i = 0; $i < $commentCount; $i++) {
                // 70% 확률로 회원 댓글, 30% 확률로 비회원 댓글
                $isGuestComment = fake()->boolean(30);
                
                if ($isGuestComment) {
                    // 비회원 댓글 생성
                    $comment = Comment::factory()
                        ->guest()
                        ->approved()
                        ->create([
                            'post_id' => $post->id,
                        ]);
                } else {
                    // 회원 댓글 생성
                    $comment = Comment::factory()
                        ->member()
                        ->approved()
                        ->create([
                            'post_id' => $post->id,
                            'user_id' => $users->random()->id,
                        ]);
                }

                // 60% 확률로 대댓글 생성
                if (fake()->boolean(60)) {
                    $replyCount = fake()->numberBetween(1, 3);
                    
                    for ($j = 0; $j < $replyCount; $j++) {
                        $isGuestReply = fake()->boolean(40);
                        
                        if ($isGuestReply) {
                            Comment::factory()
                                ->guest()
                                ->approved()
                                ->create([
                                    'post_id' => $post->id,
                                    'parent_id' => $comment->id,
                                    'depth' => 1,
                                ]);
                        } else {
                            Comment::factory()
                                ->member()
                                ->approved()
                                ->create([
                                    'post_id' => $post->id,
                                    'parent_id' => $comment->id,
                                    'user_id' => $users->random()->id,
                                    'depth' => 1,
                                ]);
                        }
                    }
                }
            }
        }

        // 일부 댓글은 대기 상태로 생성
        $pendingCommentCount = fake()->numberBetween(5, 15);
        Comment::factory()
            ->count($pendingCommentCount)
            ->pending()
            ->create([
                'post_id' => fn() => $posts->random()->id,
                'user_id' => fn() => fake()->boolean(70) ? $users->random()->id : null,
            ]);

        // 일부 댓글은 스팸으로 생성
        $spamCommentCount = fake()->numberBetween(2, 8);
        Comment::factory()
            ->count($spamCommentCount)
            ->spam()
            ->create([
                'post_id' => fn() => $posts->random()->id,
                'user_id' => fn() => fake()->boolean(20) ? $users->random()->id : null,
            ]);

        // 샘플 댓글 내용들
        $sampleComments = [
            [
                'content' => '정말 유익한 글이네요! 특히 Laravel 11의 새로운 기능들에 대한 설명이 매우 도움이 되었습니다. 감사합니다!',
                'guest_name' => '개발자김씨',
                'guest_email' => 'dev.kim@example.com',
            ],
            [
                'content' => 'Vue.js 3 Composition API에 대한 설명이 정말 자세하네요. 실제 프로젝트에 적용해보고 싶습니다.',
                'guest_name' => '프론트엔드러버',
                'guest_email' => 'frontend@example.com',
            ],
            [
                'content' => 'Docker 설정 부분에서 질문이 있습니다. nginx 설정 파일은 어떻게 작성해야 하나요?',
                'guest_name' => 'DevOps초보',
                'guest_email' => 'devops.newbie@example.com',
            ],
            [
                'content' => 'MySQL 성능 최적화 팁들이 정말 실용적이네요. 인덱스 설계 부분이 특히 도움되었습니다.',
                'guest_name' => 'DB관리자',
                'guest_email' => 'dba@example.com',
            ],
            [
                'content' => 'React Hooks 예제들이 이해하기 쉽게 잘 정리되어 있네요. useReducer 부분을 더 자세히 알고 싶습니다.',
                'guest_name' => 'React개발자',
                'guest_email' => 'react.dev@example.com',
            ],
        ];

        // 샘플 댓글들을 실제 포스트에 추가
        foreach ($sampleComments as $sampleComment) {
            $post = $posts->random();
            
            $comment = Comment::create([
                'post_id' => $post->id,
                'content' => $sampleComment['content'],
                'guest_name' => $sampleComment['guest_name'],
                'guest_email' => $sampleComment['guest_email'],
                'guest_password' => bcrypt('password123'),
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $users->where('role', 'admin')->first()->id ?? 1,
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'depth' => 0,
                'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
            ]);

            // 일부 샘플 댓글에 답글 추가
            if (fake()->boolean(70)) {
                Comment::create([
                    'post_id' => $post->id,
                    'parent_id' => $comment->id,
                    'content' => fake()->randomElement([
                        '좋은 질문이네요! 관련해서 추가 설명을 드리면...',
                        '감사합니다! 더 궁금한 점이 있으시면 언제든 물어보세요.',
                        '네, 맞습니다. 그 부분에 대해서는 별도 포스트로 다뤄보겠습니다.',
                        '실제 프로젝트에 적용하실 때 주의하실 점들을 추가로 공유드릴게요.',
                    ]),
                    'user_id' => $users->where('role', 'admin')->first()->id ?? 1,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $users->where('role', 'admin')->first()->id ?? 1,
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'depth' => 1,
                    'created_at' => fake()->dateTimeBetween($comment->created_at, 'now'),
                ]);
            }
        }

        $this->command->info('댓글 시더 완료: 댓글과 대댓글이 생성되었습니다.');
    }
}