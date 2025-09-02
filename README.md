# Laravel Light Blog API

Laravel 11과 PHP 8.3 기반의 RESTful API 블로그 백엔드 서비스입니다.

## 프로젝트 개요

AWS Lightsail(1vCPU, 1GB RAM) 환경에서 동작하는 경량/고성능 블로그 API 시스템으로, 
JWT 인증 기반의 완전한 헤드리스 CMS 백엔드를 제공합니다.

## 핵심 철학

- **가벼운 성능**: API 응답 최적화 및 효율적인 캐싱 전략
- **유지보수성**: 1:1 SEO 메타 테이블, 계층형 카테고리 구조
- **확장성**: 다양한 클라이언트 지원 가능한 RESTful API 설계
- **보안**: JWT 토큰 기반 인증 및 역할별 권한 제어


## 현재 개발 상태

이 프로젝트는 현재 **API 리팩토링 중**입니다. 웹 애플리케이션에서 순수 API 서비스로 전환 작업을 진행하고 있습니다.

### 계획된 주요 기능

#### 인증 및 권한 관리
- JWT 기반 토큰 인증 (로그인, 회원가입, 토큰 갱신)
- 역할 기반 접근 제어 (Admin, Author 권한 시스템)

#### 컨텐츠 관리 API
- 포스트 CRUD API (JSON 응답, 권한별 접근 제어)
- 계층형 카테고리 API (CRUD, 계층 구조 응답)
- 태그 관리 API (CRUD, 태그 클라우드 데이터)
- 정적 페이지 API (CRUD)
- 댓글/대댓글 API (계층 구조 응답, 스팸 필터링)

#### 검색 및 통계 API
- 통합 검색 API (포스트/페이지/카테고리/태그)
- 접속 통계 API (방문자 추적, 분석 데이터)
- 관리자 통계 API (실시간 방문자, 인기 콘텐츠)

#### 이미지 관리 API
- 이미지 업로드 API (WebP 변환/리사이즈/저장)
- 대표 이미지 및 OG 이미지 업로드 API
- 첨부파일 관리 API

---

## 기술 스택

### Backend Framework
- Laravel 11 (PHP 8.3)
- JWT Authentication (php-open-source-saver/jwt-auth)

### Database & Cache
- MariaDB 10.11 (고성능, 확장성 확보)
- Redis (캐시, 세션, 큐 저장소)

### API & Documentation
- RESTful API 설계
- OpenAPI/Swagger 규격 (계획)
- 일관된 JSON 응답 구조

### Infrastructure
- Docker 컨테이너화 (`.docker/` 디렉토리 구조)
- Nginx 웹서버
- Supervisor 프로세스 관리

### Development & Testing
- Pest 테스트 프레임워크 (재작성 예정)
- TDD 개발 방법론

---

## 설치 및 실행

### 환경 요구사항
- Docker & Docker Compose
- PHP 8.3+
- Composer
- MariaDB 10.11+
- Redis

### 통합 배포 (권장)

```bash
# 1. 프로젝트 클론
git clone https://github.com/nOo9ya/laravel-light-blog-api.git
cd laravel-light-blog-api

# 2. Docker 환경 배포
./scripts/deploy-all.sh docker --build --init-dirs

# 3. 베어메탈 서버 배포 (프로덕션)
./scripts/deploy-all.sh baremetal \
    --domain your-domain.com \
    --email admin@your-domain.com \
    --db-password your-secure-password
```

### 개발 환경 설정

```bash
# Docker Sail 개발 환경
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail composer install

# JWT 설정 (개발 진행 후 활성화)
# ./vendor/bin/sail artisan jwt:secret

# 마이그레이션 (개발 진행 후 활성화) 
# ./vendor/bin/sail artisan migrate
```

### API 접속 정보
- API Base URL: `/api/v1`
- 헬스체크: `/api/v1/system/health` (계획)
- 시스템 정보: `/api/v1/system/info` (계획)

---

## 계획된 API 구조

### 주요 엔드포인트 (개발 예정)

```
/api/v1/
├── auth/                 # JWT 기반 인증
│   ├── login            # 로그인
│   ├── register         # 회원가입  
│   ├── logout           # 로그아웃
│   ├── refresh          # 토큰 갱신
│   └── me               # 사용자 정보
├── posts/               # 포스트 CRUD
├── categories/          # 계층형 카테고리
├── tags/               # 태그 관리
├── pages/              # 정적 페이지
├── comments/           # 댓글 시스템
├── search/             # 통합 검색
├── media/              # 이미지 업로드
├── admin/              # 관리자 전용
│   ├── users/          # 사용자 관리
│   ├── analytics/      # 통계/분석
│   └── dashboard/      # 대시보드
└── system/             # 시스템 정보
    ├── health          # 헬스체크
    └── info            # 시스템 정보
```

### 인증 시스템 (계획)

```bash
# JWT 토큰 기반 인증
POST /api/v1/auth/login
Authorization: Bearer {JWT_TOKEN}

# 역할별 권한 제어
- Admin: 모든 리소스 접근
- Author: 포스트/댓글 관리
```

---

## 개발 계획

### 현재 진행 상황
- 프로젝트 상태: **API 리팩토링 중**
- 진행률: **0/7 단계** (API 전환 작업 시작)

### 개발 단계
1. 프로젝트/환경 세팅 및 API 구조 설계
2. JWT 인증/권한 시스템 API 
3. 카테고리/태그/페이지 API
4. 포스트 CRUD API, SEO/메타 정보, 이미지 관리
5. 댓글/대댓글 API, 스팸 필터링
6. 검색/통계 API 시스템
7. API 배포/최적화 구현

### 개발 환경 설정

```bash
# Docker Sail 개발환경
./vendor/bin/sail up -d

# 프로덕션 배포
./scripts/deploy-all.sh baremetal --domain example.com --email admin@example.com --db-password password
```

---

## 배포 및 관리

### 통합 배포 스크립트
```bash
# Docker 환경
./scripts/deploy-all.sh docker --build --init-dirs

# 베어메탈 서버
./scripts/deploy-all.sh baremetal --domain example.com --email admin@example.com --db-password password

# SSL 인증서만 설정
./scripts/deploy-all.sh ssl-only --domain example.com --email admin@example.com
```

### 프로젝트 관리
- 상세 배포 가이드: `README_DEPLOYMENT.md`
- 개발 규칙: `reports/CLAUDE.md`
- 개발 계획: `reports/PROJECT_PROGRESS_SUMMARY.md`

## 라이선스

MIT License

## 개발자

- GitHub: [@nOo9ya](https://github.com/nOo9ya)
- Email: noo9ya@gmail.com