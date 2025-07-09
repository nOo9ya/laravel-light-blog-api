<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('author'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $pageId = $this->route('page')?->id;

        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9가-힣\-_]+$/',
                Rule::unique('pages', 'slug')->ignore($pageId),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'min:10'],
            'template' => ['required', 'string', 'in:default,full-width,landing,contact,about,portfolio,blank'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'parent_id' => ['nullable', 'exists:pages,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_published' => ['boolean'],
            'show_in_menu' => ['boolean'],
            'menu_title' => ['nullable', 'string', 'max:255'],
            'featured_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'custom_css' => ['nullable', 'string', 'max:10000'],
            'custom_js' => ['nullable', 'string', 'max:10000'],
            
            // SEO 메타 필드
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => '페이지 제목을 입력해주세요.',
            'title.min' => '페이지 제목은 최소 2글자 이상이어야 합니다.',
            'title.max' => '페이지 제목은 255글자를 초과할 수 없습니다.',
            
            'slug.regex' => '슬러그는 영문, 숫자, 한글, 하이픈(-), 언더스코어(_)만 사용할 수 있습니다.',
            'slug.unique' => '이미 사용 중인 슬러그입니다.',
            'slug.max' => '슬러그는 255글자를 초과할 수 없습니다.',
            
            'excerpt.max' => '요약은 500글자를 초과할 수 없습니다.',
            
            'content.required' => '페이지 내용을 입력해주세요.',
            'content.min' => '페이지 내용은 최소 10글자 이상이어야 합니다.',
            
            'template.required' => '템플릿을 선택해주세요.',
            'template.in' => '올바른 템플릿을 선택해주세요.',
            
            'category_id.exists' => '존재하지 않는 카테고리입니다.',
            'parent_id.exists' => '존재하지 않는 상위 페이지입니다.',
            
            'sort_order.integer' => '정렬 순서는 숫자여야 합니다.',
            'sort_order.min' => '정렬 순서는 0 이상이어야 합니다.',
            'sort_order.max' => '정렬 순서는 999 이하여야 합니다.',
            
            'menu_title.max' => '메뉴 제목은 255글자를 초과할 수 없습니다.',
            
            'featured_image.image' => '이미지 파일만 업로드할 수 있습니다.',
            'featured_image.mimes' => 'jpeg, png, jpg, gif, webp 형식의 이미지만 업로드할 수 있습니다.',
            'featured_image.max' => '이미지 크기는 2MB를 초과할 수 없습니다.',
            
            'custom_css.max' => '사용자 정의 CSS는 10,000글자를 초과할 수 없습니다.',
            'custom_js.max' => '사용자 정의 JavaScript는 10,000글자를 초과할 수 없습니다.',
            
            'meta_title.max' => '메타 제목은 255글자를 초과할 수 없습니다.',
            'meta_description.max' => '메타 설명은 500글자를 초과할 수 없습니다.',
            'meta_keywords.max' => '메타 키워드는 500글자를 초과할 수 없습니다.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => '페이지 제목',
            'slug' => '슬러그',
            'excerpt' => '요약',
            'content' => '페이지 내용',
            'template' => '템플릿',
            'category_id' => '카테고리',
            'parent_id' => '상위 페이지',
            'sort_order' => '정렬 순서',
            'is_published' => '발행 상태',
            'show_in_menu' => '메뉴 표시',
            'menu_title' => '메뉴 제목',
            'featured_image' => '대표 이미지',
            'custom_css' => '사용자 정의 CSS',
            'custom_js' => '사용자 정의 JavaScript',
            'meta_title' => '메타 제목',
            'meta_description' => '메타 설명',
            'meta_keywords' => '메타 키워드',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // 체크박스 필드 처리
        $this->merge([
            'is_published' => $this->boolean('is_published'),
            'show_in_menu' => $this->boolean('show_in_menu'),
        ]);

        // 빈 문자열을 null로 변환
        $nullableFields = ['slug', 'excerpt', 'category_id', 'parent_id', 'sort_order', 'menu_title', 'custom_css', 'custom_js', 'meta_title', 'meta_description', 'meta_keywords'];
        
        foreach ($nullableFields as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // 이미지 업로드 처리
        if ($this->hasFile('featured_image')) {
            $path = $this->file('featured_image')->store('pages', 'public');
            $this->merge(['featured_image' => $path]);
        }

        // 정렬 순서 기본값 설정
        if (is_null($this->input('sort_order'))) {
            $maxOrder = \App\Models\Page::max('sort_order') ?? 0;
            $this->merge(['sort_order' => $maxOrder + 1]);
        }
    }
}