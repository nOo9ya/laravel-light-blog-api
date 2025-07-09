<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements MustVerifyEmail, JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 사용자가 관리자인지 확인
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * 사용자가 작성자인지 확인
     */
    public function isAuthor(): bool
    {
        return $this->role === 'author';
    }

    /**
     * 특정 역할을 가지고 있는지 확인
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * 역할 할당 (테스트 호환성)
     */
    public function assignRole(string $role): void
    {
        $this->update(['role' => $role]);
    }

    /*
    |--------------------------------------------------------------------------
    | JWT Subject 인터페이스 구현
    |--------------------------------------------------------------------------
    */

    /**
     * JWT 토큰의 식별자 반환
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT 토큰에 포함할 커스텀 클레임 반환
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'email' => $this->email,
            'name' => $this->name,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 관계형 메서드 (Relations)
    |--------------------------------------------------------------------------
    */

    /**
     * 사용자가 작성한 포스트
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * 사용자가 작성한 댓글
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * 사용자가 작성한 페이지
     */
    public function pages()
    {
        return $this->hasMany(Page::class);
    }
}
