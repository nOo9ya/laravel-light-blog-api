#!/bin/bash

# Docker 호스트 디렉토리 초기화 스크립트
# 데이터베이스, 로그, SSL 인증서 디렉토리를 생성하고 권한을 설정

set -e

# 로그 함수
log_info() {
    echo -e "\033[0;34m[INFO]\033[0m $1"
}

log_success() {
    echo -e "\033[0;32m[SUCCESS]\033[0m $1"
}

# 프로젝트 루트 디렉토리로 이동
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$PROJECT_ROOT"

log_info "Docker 호스트 디렉토리 초기화 시작..."
log_info "프로젝트 루트: $PROJECT_ROOT"

# 데이터베이스 디렉토리 생성
log_info "데이터베이스 디렉토리 생성 중..."
mkdir -p .database/mariadb
mkdir -p .database/redis

# 로그 디렉토리 생성
log_info "로그 디렉토리 생성 중..."
mkdir -p .logs/nginx
mkdir -p .logs/laravel
mkdir -p .logs/mariadb
mkdir -p .logs/redis
mkdir -p .logs/supervisor

# SSL 인증서 디렉토리 생성
log_info "SSL 인증서 디렉토리 생성 중..."
mkdir -p .ssl/letsencrypt
mkdir -p .ssl/letsencrypt-lib

# 백업 디렉토리 생성
log_info "백업 디렉토리 생성 중..."
mkdir -p backups

# 권한 설정
log_info "디렉토리 권한 설정 중..."

# 데이터베이스 디렉토리 권한 (MariaDB: mysql:mysql, Redis: redis:redis)
sudo chown -R 999:999 .database/mariadb      # MariaDB 컨테이너 사용자
sudo chown -R 999:999 .database/redis        # Redis 컨테이너 사용자
sudo chmod -R 755 .database

# 로그 디렉토리 권한
sudo chown -R $USER:$USER .logs
sudo chmod -R 755 .logs

# nginx 로그는 특별히 nginx 사용자 권한이 필요할 수 있음
sudo chown -R 101:101 .logs/nginx            # nginx alpine 이미지 사용자

# MariaDB 로그 권한
sudo chown -R 999:999 .logs/mariadb          # MariaDB 컨테이너 사용자

# SSL 디렉토리 권한
sudo chown -R $USER:$USER .ssl
sudo chmod -R 755 .ssl

# 백업 디렉토리 권한
sudo chown -R $USER:$USER backups
sudo chmod -R 755 backups

# 디렉토리 구조 출력
log_success "디렉토리 구조가 생성되었습니다:"
echo ""
echo "📁 프로젝트 루트"
echo "├── 📁 .database/"
echo "│   ├── 📁 mariadb/     (MariaDB 데이터 파일)"
echo "│   └── 📁 redis/       (Redis 데이터 파일)"
echo "├── 📁 .logs/"
echo "│   ├── 📁 nginx/       (Nginx 로그)"
echo "│   ├── 📁 laravel/     (Laravel 애플리케이션 로그)"
echo "│   ├── 📁 mariadb/     (MariaDB Slow Query 로그)"
echo "│   ├── 📁 redis/       (Redis 로그)"
echo "│   └── 📁 supervisor/  (Supervisor 로그)"
echo "├── 📁 .ssl/"
echo "│   ├── 📁 letsencrypt/ (Let's Encrypt 인증서)"
echo "│   └── 📁 letsencrypt-lib/ (Let's Encrypt 라이브러리)"
echo "└── 📁 backups/         (백업 파일)"
echo ""

log_success "Docker 호스트 디렉토리 초기화 완료!"
log_info "이제 'docker-compose up -d'를 실행할 수 있습니다."