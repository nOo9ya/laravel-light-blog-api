<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z0-9가-힣\-_]+$/',
                Rule::unique('categories', 'slug')->ignore($categoryId)
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['post', 'page', 'both'])],
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    // 자기 자신을 부모로 설정하는 것 방지
                    if ($value == $categoryId) {
                        $fail('카테고리는 자기 자신을 부모 카테고리로 설정할 수 없습니다.');
                    }
                    
                    // 순환 참조 방지 - 자식 카테고리를 부모로 설정하는 것 방지
                    if ($value && $categoryId) {
                        $childIds = $this->getChildCategoryIds($categoryId);
                        if (in_array($value, $childIds)) {
                            $fail('하위 카테고리를 부모 카테고리로 설정할 수 없습니다.');
                        }
                    }
                }
            ],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * 사용자 정의 오류 메시지
     */
    public function messages(): array
    {
        return [
            'name.required' => '카테고리 이름을 입력해주세요.',
            'name.min' => '카테고리 이름은 최소 2글자 이상 입력해주세요.',
            'name.max' => '카테고리 이름은 255글자를 초과할 수 없습니다.',
            
            'slug.required' => '카테고리 슬러그를 입력해주세요.',
            'slug.min' => '슬러그는 최소 2글자 이상 입력해주세요.',
            'slug.max' => '슬러그는 255글자를 초과할 수 없습니다.',
            'slug.regex' => '슬러그는 영문, 숫자, 한글, 하이픈(-), 언더스코어(_)만 사용할 수 있습니다.',
            'slug.unique' => '이미 사용 중인 슬러그입니다. 다른 슬러그를 입력해주세요.',
            
            'description.max' => '카테고리 설명은 1000글자를 초과할 수 없습니다.',
            
            'type.required' => '카테고리 타입을 선택해주세요.',
            'type.in' => '카테고리 타입은 포스트용, 페이지용, 공용 중 하나여야 합니다.',
            
            'parent_id.exists' => '선택한 부모 카테고리가 존재하지 않습니다.',
            
            'order.integer' => '정렬 순서는 숫자만 입력할 수 있습니다.',
            'order.min' => '정렬 순서는 0 이상이어야 합니다.',
            'order.max' => '정렬 순서는 999를 초과할 수 없습니다.',
            
            'is_active.required' => '활성화 상태를 선택해주세요.',
            'is_active.boolean' => '활성화 상태가 올바르지 않습니다.',
        ];
    }

    /**
     * 유효성 검사할 필드명
     */
    public function attributes(): array
    {
        return [
            'name' => '카테고리 이름',
            'slug' => '슬러그',
            'description' => '설명',
            'type' => '타입',
            'parent_id' => '부모 카테고리',
            'order' => '정렬 순서',
            'is_active' => '활성화 상태',
        ];
    }

    /**
     * 유효성 검사 준비
     */
    protected function prepareForValidation(): void
    {
        // 슬러그가 비어있으면 이름으로부터 자동 생성
        if (empty($this->slug) && !empty($this->name)) {
            $this->merge([
                'slug' => \Str::slug($this->name, '-', 'ko')
            ]);
        }

        // order가 비어있으면 0으로 설정
        if (empty($this->order)) {
            $this->merge([
                'order' => 0
            ]);
        }

        // is_active가 설정되지 않았으면 true로 설정
        if (!isset($this->is_active)) {
            $this->merge([
                'is_active' => true
            ]);
        }
    }

    /**
     * 특정 카테고리의 모든 하위 카테고리 ID를 재귀적으로 가져오기
     */
    private function getChildCategoryIds(int $categoryId): array
    {
        $childIds = [];
        $directChildren = \App\Models\Category::where('parent_id', $categoryId)->pluck('id')->toArray();
        
        foreach ($directChildren as $childId) {
            $childIds[] = $childId;
            $childIds = array_merge($childIds, $this->getChildCategoryIds($childId));
        }
        
        return $childIds;
    }
}