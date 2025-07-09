#!/bin/bash

# Docker 컨테이너 내부 cron 설정 스크립트
# Laravel 스케줄러 및 기타 주기적 작업 설정

set -e

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

# 컨테이너 환경 확인
if [ ! -f /.dockerenv ]; then
    log_error "이 스크립트는 Docker 컨테이너 내부에서만 실행되어야 합니다."
    exit 1
fi

log_info "Docker 컨테이너 cron 설정 시작..."

# 기존 crontab 백업 (있다면)
if crontab -l > /dev/null 2>&1; then
    log_info "기존 crontab 백업 중..."
    crontab -l > /tmp/crontab.backup
fi

# 새로운 crontab 설정 생성
log_info "새로운 crontab 설정 생성 중..."
cat > /tmp/crontab.new << 'EOF'
# Laravel Light Blog API - Cron 작업 설정
# 작성일: $(date)

# Laravel 스케줄러 (매분 실행)
* * * * * www cd /var/www/html && php artisan schedule:run >> /var/log/cron.log 2>&1

# 로그 로테이션 확인 (매일 새벽 1시)
0 1 * * * root /usr/sbin/logrotate /etc/logrotate.conf

# 임시 파일 정리 (매일 새벽 3시)
0 3 * * * www find /var/www/html/storage/framework/cache -name "*.php" -mtime +1 -delete 2>/dev/null

# 세션 파일 정리 (매일 새벽 4시)
0 4 * * * www find /var/www/html/storage/framework/sessions -name "sess_*" -mtime +1 -delete 2>/dev/null

EOF

# crontab 적용
log_info "crontab 적용 중..."
crontab /tmp/crontab.new

# cron 서비스가 실행 중인지 확인
if ! pgrep -x "crond" > /dev/null; then
    log_info "cron 데몬 시작 중..."
    crond
fi

# 설정 확인
log_info "현재 crontab 설정 확인:"
crontab -l

# 로그 파일 생성
log_info "로그 파일 초기화..."
touch /var/log/cron.log
chmod 644 /var/log/cron.log

# 정리
rm -f /tmp/crontab.new

log_success "Docker 컨테이너 cron 설정 완료!"
log_info "cron 로그 확인: tail -f /var/log/cron.log"
log_info "Laravel 스케줄러 테스트: php artisan schedule:run -v"