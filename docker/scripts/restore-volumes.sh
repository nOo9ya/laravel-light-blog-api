#!/bin/bash

# Docker Volumes 복원 스크립트
# 백업된 볼륨을 복원

set -e

# 사용법 출력
usage() {
    echo "사용법: $0 <백업_디렉토리>"
    echo "예시: $0 /var/backups/laravel-blog-api/20240706_120000"
    exit 1
}

# 매개변수 확인
if [ $# -eq 0 ]; then
    usage
fi

BACKUP_PATH="$1"

# 백업 디렉토리 존재 확인
if [ ! -d "$BACKUP_PATH" ]; then
    echo "오류: 백업 디렉토리가 존재하지 않습니다: $BACKUP_PATH"
    exit 1
fi

# 로그 함수
log_info() {
    echo -e "\033[0;34m[INFO]\033[0m $1"
}

log_success() {
    echo -e "\033[0;32m[SUCCESS]\033[0m $1"
}

log_error() {
    echo -e "\033[0;31m[ERROR]\033[0m $1"
}

log_warning() {
    echo -e "\033[1;33m[WARNING]\033[0m $1"
}

# Docker Compose 프로젝트 이름
PROJECT_NAME="laravel-light-blog-api"

log_info "Docker Volumes 복원 시작"
log_info "백업 경로: $BACKUP_PATH"

# 사용자 확인
log_warning "이 작업은 기존 데이터를 덮어씁니다!"
read -p "계속 진행하시겠습니까? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_info "복원 작업이 취소되었습니다."
    exit 0
fi

# Docker Compose 중지
log_info "Docker Compose 서비스 중지 중..."
docker-compose down

# 1. MariaDB 데이터 복원
if [ -f "$BACKUP_PATH/mariadb_data.tar.gz" ]; then
    log_info "MariaDB 데이터 복원 중..."
    docker volume rm "${PROJECT_NAME}_mariadb_data" 2>/dev/null || true
    docker volume create "${PROJECT_NAME}_mariadb_data"
    docker run --rm \
        -v "${PROJECT_NAME}_mariadb_data:/data" \
        -v "$BACKUP_PATH:/backup" \
        alpine:latest \
        tar xzf /backup/mariadb_data.tar.gz -C /data
    log_success "MariaDB 데이터 복원 완료"
else
    log_warning "MariaDB 데이터 백업 파일을 찾을 수 없습니다."
fi

# 2. MariaDB 로그 복원
if [ -f "$BACKUP_PATH/mariadb_logs.tar.gz" ]; then
    log_info "MariaDB 로그 복원 중..."
    docker volume rm "${PROJECT_NAME}_mariadb_logs" 2>/dev/null || true
    docker volume create "${PROJECT_NAME}_mariadb_logs"
    docker run --rm \
        -v "${PROJECT_NAME}_mariadb_logs:/data" \
        -v "$BACKUP_PATH:/backup" \
        alpine:latest \
        tar xzf /backup/mariadb_logs.tar.gz -C /data
    log_success "MariaDB 로그 복원 완료"
else
    log_warning "MariaDB 로그 백업 파일을 찾을 수 없습니다."
fi

# 3. Laravel 로그 복원
if [ -f "$BACKUP_PATH/laravel_logs.tar.gz" ]; then
    log_info "Laravel 로그 복원 중..."
    docker volume rm "${PROJECT_NAME}_laravel_logs" 2>/dev/null || true
    docker volume create "${PROJECT_NAME}_laravel_logs"
    docker run --rm \
        -v "${PROJECT_NAME}_laravel_logs:/data" \
        -v "$BACKUP_PATH:/backup" \
        alpine:latest \
        tar xzf /backup/laravel_logs.tar.gz -C /data
    log_success "Laravel 로그 복원 완료"
else
    log_warning "Laravel 로그 백업 파일을 찾을 수 없습니다."
fi

# 4. Laravel 스토리지 복원
if [ -f "$BACKUP_PATH/laravel_storage.tar.gz" ]; then
    log_info "Laravel 스토리지 복원 중..."
    docker volume rm "${PROJECT_NAME}_laravel_storage" 2>/dev/null || true
    docker volume create "${PROJECT_NAME}_laravel_storage"
    docker run --rm \
        -v "${PROJECT_NAME}_laravel_storage:/data" \
        -v "$BACKUP_PATH:/backup" \
        alpine:latest \
        tar xzf /backup/laravel_storage.tar.gz -C /data
    log_success "Laravel 스토리지 복원 완료"
else
    log_warning "Laravel 스토리지 백업 파일을 찾을 수 없습니다."
fi

# 5. Nginx 로그 복원
if [ -f "$BACKUP_PATH/nginx_logs.tar.gz" ]; then
    log_info "Nginx 로그 복원 중..."
    docker volume rm "${PROJECT_NAME}_nginx_logs" 2>/dev/null || true
    docker volume create "${PROJECT_NAME}_nginx_logs"
    docker run --rm \
        -v "${PROJECT_NAME}_nginx_logs:/data" \
        -v "$BACKUP_PATH:/backup" \
        alpine:latest \
        tar xzf /backup/nginx_logs.tar.gz -C /data
    log_success "Nginx 로그 복원 완료"
else
    log_warning "Nginx 로그 백업 파일을 찾을 수 없습니다."
fi

# 6. Let's Encrypt 인증서 복원
if [ -f "$BACKUP_PATH/letsencrypt_data.tar.gz" ]; then
    log_info "Let's Encrypt 인증서 복원 중..."
    docker volume rm "${PROJECT_NAME}_letsencrypt_data" 2>/dev/null || true
    docker volume create "${PROJECT_NAME}_letsencrypt_data"
    docker run --rm \
        -v "${PROJECT_NAME}_letsencrypt_data:/data" \
        -v "$BACKUP_PATH:/backup" \
        alpine:latest \
        tar xzf /backup/letsencrypt_data.tar.gz -C /data
    log_success "Let's Encrypt 인증서 복원 완료"
else
    log_warning "Let's Encrypt 인증서 백업 파일을 찾을 수 없습니다."
fi

if [ -f "$BACKUP_PATH/letsencrypt_lib.tar.gz" ]; then
    log_info "Let's Encrypt 라이브러리 복원 중..."
    docker volume rm "${PROJECT_NAME}_letsencrypt_lib" 2>/dev/null || true
    docker volume create "${PROJECT_NAME}_letsencrypt_lib"
    docker run --rm \
        -v "${PROJECT_NAME}_letsencrypt_lib:/data" \
        -v "$BACKUP_PATH:/backup" \
        alpine:latest \
        tar xzf /backup/letsencrypt_lib.tar.gz -C /data
    log_success "Let's Encrypt 라이브러리 복원 완료"
else
    log_warning "Let's Encrypt 라이브러리 백업 파일을 찾을 수 없습니다."
fi

# 7. Redis 데이터 복원
if [ -f "$BACKUP_PATH/redis_data.tar.gz" ]; then
    log_info "Redis 데이터 복원 중..."
    docker volume rm "${PROJECT_NAME}_redis_data" 2>/dev/null || true
    docker volume create "${PROJECT_NAME}_redis_data"
    docker run --rm \
        -v "${PROJECT_NAME}_redis_data:/data" \
        -v "$BACKUP_PATH:/backup" \
        alpine:latest \
        tar xzf /backup/redis_data.tar.gz -C /data
    log_success "Redis 데이터 복원 완료"
else
    log_warning "Redis 데이터 백업 파일을 찾을 수 없습니다."
fi

# Docker Compose 재시작
log_info "Docker Compose 서비스 재시작 중..."
docker-compose up -d

# 서비스 상태 확인
log_info "서비스 상태 확인 중..."
sleep 10
docker-compose ps

log_success "Docker Volumes 복원 완료!"
log_info "백업 정보를 확인하려면 다음 파일을 참조하세요:"
log_info "$BACKUP_PATH/backup_info.txt"