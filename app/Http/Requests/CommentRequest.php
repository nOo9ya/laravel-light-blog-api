<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommentRequest extends FormRequest
{
    /**
     * 사용자가 이 요청을 할 권한이 있는지 확인
     */
    public function authorize(): bool
    {
        // 댓글 작성은 모든 사용자(비회원 포함) 허용
        return true;
    }

    /**
     * 요청에 적용할 유효성 검사 규칙
     */
    public function rules(): array
    {
        $rules = [
            'content' => ['required', 'string', 'min:5', 'max:2000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
        ];

        // 비회원 댓글인 경우 추가 필드 검증
        if (!auth()->check()) {
            $rules = array_merge($rules, [
                'name' => ['required', 'string', 'min:2', 'max:50'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:4', 'max:50'],
                'website' => ['nullable', 'url', 'max:255'],
            ]);
        }

        return $rules;
    }

    /**
     * 사용자 정의 오류 메시지
     */
    public function messages(): array
    {
        return [
            'content.required' => '댓글 내용을 입력해주세요.',
            'content.min' => '댓글은 최소 5글자 이상 입력해주세요.',
            'content.max' => '댓글은 2000글자를 초과할 수 없습니다.',
            
            'parent_id.exists' => '답글을 달 댓글이 존재하지 않습니다.',
            
            'name.required' => '이름을 입력해주세요.',
            'name.min' => '이름은 최소 2글자 이상 입력해주세요.',
            'name.max' => '이름은 50글자를 초과할 수 없습니다.',
            
            'email.required' => '이메일을 입력해주세요.',
            'email.email' => '올바른 이메일 형식이 아닙니다.',
            'email.max' => '이메일은 255글자를 초과할 수 없습니다.',
            
            'password.required' => '비밀번호를 입력해주세요.',
            'password.min' => '비밀번호는 최소 4글자 이상 입력해주세요.',
            'password.max' => '비밀번호는 50글자를 초과할 수 없습니다.',
            
            'website.url' => '올바른 웹사이트 URL 형식이 아닙니다.',
            'website.max' => '웹사이트 URL은 255글자를 초과할 수 없습니다.',
        ];
    }

    /**
     * 유효성 검사할 필드명
     */
    public function attributes(): array
    {
        return [
            'content' => '댓글 내용',
            'parent_id' => '답글 대상',
            'name' => '이름',
            'email' => '이메일',
            'password' => '비밀번호',
            'website' => '웹사이트',
        ];
    }

    /**
     * 유효성 검사 준비
     */
    protected function prepareForValidation(): void
    {
        // 댓글 내용에서 불필요한 공백 제거
        if ($this->content) {
            $this->merge([
                'content' => trim($this->content)
            ]);
        }

        // 웹사이트 URL에 http:// 추가 (없는 경우)
        if ($this->website && !preg_match('/^https?:\/\//', $this->website)) {
            $this->merge([
                'website' => 'https://' . $this->website
            ]);
        }
    }
}