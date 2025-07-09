<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
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
        $tagId = $this->route('tag')?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z0-9가-힣\-_]+$/',
                Rule::unique('tags', 'slug')->ignore($tagId)
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => [
                'nullable',
                'string',
                'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'
            ],
        ];
    }

    /**
     * 사용자 정의 오류 메시지
     */
    public function messages(): array
    {
        return [
            'name.required' => '태그 이름을 입력해주세요.',
            'name.min' => '태그 이름은 최소 2글자 이상 입력해주세요.',
            'name.max' => '태그 이름은 255글자를 초과할 수 없습니다.',
            
            'slug.required' => '태그 슬러그를 입력해주세요.',
            'slug.min' => '슬러그는 최소 2글자 이상 입력해주세요.',
            'slug.max' => '슬러그는 255글자를 초과할 수 없습니다.',
            'slug.regex' => '슬러그는 영문, 숫자, 한글, 하이픈(-), 언더스코어(_)만 사용할 수 있습니다.',
            'slug.unique' => '이미 사용 중인 슬러그입니다. 다른 슬러그를 입력해주세요.',
            
            'description.max' => '태그 설명은 1000글자를 초과할 수 없습니다.',
            
            'color.regex' => '색상은 올바른 HEX 형식(예: #3b82f6)이어야 합니다.',
        ];
    }

    /**
     * 유효성 검사할 필드명
     */
    public function attributes(): array
    {
        return [
            'name' => '태그 이름',
            'slug' => '슬러그',
            'description' => '설명',
            'color' => '색상',
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

        // 색상이 비어있으면 기본값 설정
        if (empty($this->color)) {
            $this->merge([
                'color' => '#3b82f6'
            ]);
        }
    }
}