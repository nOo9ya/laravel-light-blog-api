#!/bin/bash

# Docker Volumes 백업 스크립트
# 모든 중요한 데이터와 로그를 로컬 디렉토리에 백업

set -e

# 백업 디렉토리 설정
BACKUP_DIR="/var/backups/laravel-blog-api"
DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_PATH="$BACKUP_DIR/$DATE"

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

# 백업 디렉토리 생성
mkdir -p "$BACKUP_PATH"

# Docker Compose 프로젝트 이름 (디렉토리 이름 기반)
PROJECT_NAME="laravel-light-blog-api"

log_info "Docker Volumes 백업 시작: $DATE"

# 1. MariaDB 데이터 백업
log_info "MariaDB 데이터 백업 중..."
docker run --rm \
    -v "${PROJECT_NAME}_mariadb_data:/data" \
    -v "$BACKUP_PATH:/backup" \
    alpine:latest \
    tar czf /backup/mariadb_data.tar.gz -C /data .

# 2. MariaDB 로그 백업
log_info "MariaDB 로그 백업 중..."
docker run --rm \
    -v "${PROJECT_NAME}_mariadb_logs:/data" \
    -v "$BACKUP_PATH:/backup" \
    alpine:latest \
    tar czf /backup/mariadb_logs.tar.gz -C /data .

# 3. Laravel 로그 백업
log_info "Laravel 로그 백업 중..."
docker run --rm \
    -v "${PROJECT_NAME}_laravel_logs:/data" \
    -v "$BACKUP_PATH:/backup" \
    alpine:latest \
    tar czf /backup/laravel_logs.tar.gz -C /data .

# 4. Laravel 스토리지 백업
log_info "Laravel 스토리지 백업 중..."
docker run --rm \
    -v "${PROJECT_NAME}_laravel_storage:/data" \
    -v "$BACKUP_PATH:/backup" \
    alpine:latest \
    tar czf /backup/laravel_storage.tar.gz -C /data .

# 5. Nginx 로그 백업
log_info "Nginx 로그 백업 중..."
docker run --rm \
    -v "${PROJECT_NAME}_nginx_logs:/data" \
    -v "$BACKUP_PATH:/backup" \
    alpine:latest \
    tar czf /backup/nginx_logs.tar.gz -C /data .

# 6. Let's Encrypt 인증서 백업
log_info "Let's Encrypt 인증서 백업 중..."
docker run --rm \
    -v "${PROJECT_NAME}_letsencrypt_data:/data" \
    -v "$BACKUP_PATH:/backup" \
    alpine:latest \
    tar czf /backup/letsencrypt_data.tar.gz -C /data .

docker run --rm \
    -v "${PROJECT_NAME}_letsencrypt_lib:/data" \
    -v "$BACKUP_PATH:/backup" \
    alpine:latest \
    tar czf /backup/letsencrypt_lib.tar.gz -C /data .

# 7. Redis 데이터 백업
log_info "Redis 데이터 백업 중..."
docker run --rm \
    -v "${PROJECT_NAME}_redis_data:/data" \
    -v "$BACKUP_PATH:/backup" \
    alpine:latest \
    tar czf /backup/redis_data.tar.gz -C /data .

# 백업 정보 파일 생성
log_info "백업 정보 파일 생성 중..."
cat > "$BACKUP_PATH/backup_info.txt" << EOF
백업 생성 시간: $(date)
백업 경로: $BACKUP_PATH
프로젝트: $PROJECT_NAME

포함된 백업:
- mariadb_data.tar.gz: MariaDB 데이터베이스 파일
- mariadb_logs.tar.gz: MariaDB 로그 파일
- laravel_logs.tar.gz: Laravel 애플리케이션 로그
- laravel_storage.tar.gz: Laravel 스토리지 (업로드 파일 등)
- nginx_logs.tar.gz: Nginx 웹서버 로그
- letsencrypt_data.tar.gz: Let's Encrypt 인증서 데이터
- letsencrypt_lib.tar.gz: Let's Encrypt 인증서 라이브러리
- redis_data.tar.gz: Redis 캐시 데이터

복원 방법:
1. Docker Compose 중지: docker-compose down
2. Volume 삭제: docker volume rm [volume_name]
3. 백업 파일 압축 해제: tar xzf backup_file.tar.gz
4. Docker Compose 재시작: docker-compose up -d
EOF

# 백업 크기 확인
BACKUP_SIZE=$(du -sh "$BACKUP_PATH" | cut -f1)
log_success "백업 완료!"
log_success "백업 경로: $BACKUP_PATH"
log_success "백업 크기: $BACKUP_SIZE"

# 오래된 백업 정리 (30일 이상)
log_info "오래된 백업 정리 중 (30일 이상)..."
find "$BACKUP_DIR" -type d -name "*_*" -mtime +30 -exec rm -rf {} \; 2>/dev/null || true

log_success "Docker Volumes 백업 작업 완료!"