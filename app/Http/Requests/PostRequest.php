<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    /**
     * 사용자가 이 요청을 할 권한이 있는지 확인
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin') || $this->user()->hasRole('author');
    }

    /**
     * 요청에 적용할 유효성 검사 규칙
     */
    public function rules(): array
    {
        $postId = $this->route('post')?->id;

        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z0-9가-힣\-_]+$/',
                Rule::unique('posts', 'slug')->ignore($postId)
            ],
            'content' => ['required', 'string', 'min:10'],
            'summary' => ['nullable', 'string', 'max:500'],
            'timeline_json' => ['nullable', 'json'],
            'main_image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120' // 5MB
            ],
            'og_image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048', // 2MB
                'dimensions:min_width=1200,min_height=630'
            ],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date', 'after_or_equal:today'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            
            // SEO 메타 데이터
            'seo.og_title' => ['nullable', 'string', 'max:255'],
            'seo.og_description' => ['nullable', 'string', 'max:500'],
            'seo.og_type' => ['nullable', 'string', 'max:50'],
            'seo.twitter_card' => ['nullable', 'string', 'max:50'],
            'seo.twitter_title' => ['nullable', 'string', 'max:255'],
            'seo.twitter_description' => ['nullable', 'string', 'max:500'],
            'seo.canonical_url' => ['nullable', 'url', 'max:255'],
            'seo.meta_keywords' => ['nullable', 'string', 'max:500'],
            'seo.robots' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * 사용자 정의 오류 메시지
     */
    public function messages(): array
    {
        return [
            'title.required' => '포스트 제목을 입력해주세요.',
            'title.min' => '포스트 제목은 최소 2글자 이상 입력해주세요.',
            'title.max' => '포스트 제목은 255글자를 초과할 수 없습니다.',
            
            'slug.required' => '포스트 슬러그를 입력해주세요.',
            'slug.min' => '슬러그는 최소 2글자 이상 입력해주세요.',
            'slug.max' => '슬러그는 255글자를 초과할 수 없습니다.',
            'slug.regex' => '슬러그는 영문, 숫자, 한글, 하이픈(-), 언더스코어(_)만 사용할 수 있습니다.',
            'slug.unique' => '이미 사용 중인 슬러그입니다. 다른 슬러그를 입력해주세요.',
            
            'content.required' => '포스트 내용을 입력해주세요.',
            'content.min' => '포스트 내용은 최소 10글자 이상 입력해주세요.',
            
            'summary.max' => '요약은 500글자를 초과할 수 없습니다.',
            
            'timeline_json.json' => '타임라인 데이터 형식이 올바르지 않습니다.',
            
            'main_image.image' => '대표 이미지는 이미지 파일만 업로드할 수 있습니다.',
            'main_image.mimes' => '대표 이미지는 jpeg, png, jpg, gif, webp 형식만 지원합니다.',
            'main_image.max' => '대표 이미지 크기는 5MB를 초과할 수 없습니다.',
            
            'og_image.image' => 'OG 이미지는 이미지 파일만 업로드할 수 있습니다.',
            'og_image.mimes' => 'OG 이미지는 jpeg, png, jpg 형식만 지원합니다.',
            'og_image.max' => 'OG 이미지 크기는 2MB를 초과할 수 없습니다.',
            'og_image.dimensions' => 'OG 이미지는 최소 1200x630 크기여야 합니다.',
            
            'status.required' => '포스트 상태를 선택해주세요.',
            'status.in' => '포스트 상태는 임시저장, 발행, 보관 중 하나여야 합니다.',
            
            'published_at.date' => '발행일시가 올바르지 않습니다.',
            'published_at.after_or_equal' => '발행일시는 오늘 이후 날짜만 선택할 수 있습니다.',
            
            'category_id.exists' => '선택한 카테고리가 존재하지 않습니다.',
            
            'tags.array' => '태그 데이터 형식이 올바르지 않습니다.',
            'tags.*.exists' => '선택한 태그 중 존재하지 않는 태그가 있습니다.',
            
            // SEO 메타 데이터 메시지
            'seo.og_title.max' => 'OG 제목은 255글자를 초과할 수 없습니다.',
            'seo.og_description.max' => 'OG 설명은 500글자를 초과할 수 없습니다.',
            'seo.og_type.max' => 'OG 타입은 50글자를 초과할 수 없습니다.',
            'seo.twitter_card.max' => '트위터 카드 타입은 50글자를 초과할 수 없습니다.',
            'seo.twitter_title.max' => '트위터 제목은 255글자를 초과할 수 없습니다.',
            'seo.twitter_description.max' => '트위터 설명은 500글자를 초과할 수 없습니다.',
            'seo.canonical_url.url' => '표준 URL 형식이 올바르지 않습니다.',
            'seo.canonical_url.max' => '표준 URL은 255글자를 초과할 수 없습니다.',
            'seo.meta_keywords.max' => '메타 키워드는 500글자를 초과할 수 없습니다.',
            'seo.robots.max' => '로봇 설정은 100글자를 초과할 수 없습니다.',
        ];
    }

    /**
     * 유효성 검사할 필드명
     */
    public function attributes(): array
    {
        return [
            'title' => '제목',
            'slug' => '슬러그',
            'content' => '내용',
            'summary' => '요약',
            'timeline_json' => '타임라인',
            'main_image' => '대표 이미지',
            'og_image' => 'OG 이미지',
            'status' => '상태',
            'published_at' => '발행일시',
            'category_id' => '카테고리',
            'tags' => '태그',
            'seo.og_title' => 'OG 제목',
            'seo.og_description' => 'OG 설명',
            'seo.og_type' => 'OG 타입',
            'seo.twitter_card' => '트위터 카드',
            'seo.twitter_title' => '트위터 제목',
            'seo.twitter_description' => '트위터 설명',
            'seo.canonical_url' => '표준 URL',
            'seo.meta_keywords' => '메타 키워드',
            'seo.robots' => '로봇 설정',
        ];
    }

    /**
     * 유효성 검사 준비
     */
    protected function prepareForValidation(): void
    {
        // 슬러그가 비어있으면 제목으로부터 자동 생성
        if (empty($this->slug) && !empty($this->title)) {
            $this->merge([
                'slug' => \Str::slug($this->title, '-', 'ko')
            ]);
        }

        // published_at이 비어있고 status가 published면 현재 시간으로 설정
        if (empty($this->published_at) && $this->status === 'published') {
            $this->merge([
                'published_at' => now()
            ]);
        }
    }
}