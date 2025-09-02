#!/bin/bash

# 시스템 서비스 등록 및 자동 시작 설정 스크립트
# Laravel Light Blog용

set -e

# 컬러 출력 설정
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Root 권한 확인
if [ "$EUID" -ne 0 ]; then
    log_error "이 스크립트는 root 권한으로 실행해야 합니다."
    echo "sudo $0 으로 실행해주세요."
    exit 1
fi

# 환경 변수 설정
PROJECT_DIR="/var/www/laravel-light-blog"
APP_USER="www-data"
DOMAIN=""

# 매개변수 파싱
while [[ $# -gt 0 ]]; do
    case $1 in
        --project-dir)
            PROJECT_DIR="$2"
            shift 2
            ;;
        --app-user)
            APP_USER="$2"
            shift 2
            ;;
        --domain)
            DOMAIN="$2"
            shift 2
            ;;
        --help)
            echo "사용법: sudo $0 [옵션]"
            echo ""
            echo "옵션:"
            echo "  --project-dir   Laravel 프로젝트 경로 (기본: /var/www/laravel-light-blog)"
            echo "  --app-user      애플리케이션 사용자 (기본: www-data)"
            echo "  --domain        도메인명 (모니터링용)"
            echo ""
            echo "예제:"
            echo "  sudo $0 --project-dir /var/www/myblog --domain myblog.com"
            exit 0
            ;;
        *)
            log_error "알 수 없는 옵션: $1"
            exit 1
            ;;
    esac
done

log_info "시스템 서비스 설정을 시작합니다..."
log_info "프로젝트 경로: $PROJECT_DIR"
log_info "애플리케이션 사용자: $APP_USER"

# 1. Laravel Queue Worker 서비스 생성
log_info "Laravel Queue Worker 서비스 생성 중..."
cat > /etc/systemd/system/laravel-queue.service << EOF
[Unit]
Description=Laravel Queue Worker
After=network.target
Requires=mariadb.service redis-server.service

[Service]
Type=simple
User=$APP_USER
Group=$APP_USER
Restart=always
RestartSec=5
ExecStart=/usr/bin/php $PROJECT_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=$PROJECT_DIR
Environment=LARAVEL_ENV=production

# 메모리 제한
MemoryMax=256M
MemoryHigh=200M

# 파일 디스크립터 제한
LimitNOFILE=65536

# 로그 설정
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-queue

[Install]
WantedBy=multi-user.target
EOF

# 2. Laravel Horizon 서비스 생성 (큐 모니터링)
log_info "Laravel Horizon 서비스 생성 중..."
cat > /etc/systemd/system/laravel-horizon.service << EOF
[Unit]
Description=Laravel Horizon
After=network.target
Requires=mariadb.service redis-server.service

[Service]
Type=simple
User=$APP_USER
Group=$APP_USER
Restart=always
RestartSec=5
ExecStart=/usr/bin/php $PROJECT_DIR/artisan horizon
WorkingDirectory=$PROJECT_DIR
Environment=LARAVEL_ENV=production

# 메모리 제한
MemoryMax=512M
MemoryHigh=400M

# 파일 디스크립터 제한
LimitNOFILE=65536

# 로그 설정
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-horizon

[Install]
WantedBy=multi-user.target
EOF

# 3. Laravel Scheduler 서비스 생성
log_info "Laravel Scheduler 서비스 생성 중..."
cat > /etc/systemd/system/laravel-scheduler.service << EOF
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
Type=oneshot
User=$APP_USER
Group=$APP_USER
ExecStart=/usr/bin/php $PROJECT_DIR/artisan schedule:run
WorkingDirectory=$PROJECT_DIR
Environment=LARAVEL_ENV=production
EOF

cat > /etc/systemd/system/laravel-scheduler.timer << EOF
[Unit]
Description=Run Laravel Scheduler every minute
Requires=laravel-scheduler.service

[Timer]
OnCalendar=minutely
Persistent=true

[Install]
WantedBy=timers.target
EOF

# 4. 애플리케이션 헬스체크 서비스
log_info "헬스체크 서비스 생성 중..."
cat > /etc/systemd/system/laravel-healthcheck.service << EOF
[Unit]
Description=Laravel Application Health Check
After=network.target

[Service]
Type=oneshot
User=$APP_USER
Group=$APP_USER
ExecStart=/bin/bash -c 'curl -f http://localhost/health || exit 1'
WorkingDirectory=$PROJECT_DIR
EOF

cat > /etc/systemd/system/laravel-healthcheck.timer << EOF
[Unit]
Description=Run Laravel Health Check every 5 minutes
Requires=laravel-healthcheck.service

[Timer]
OnCalendar=*:0/5
Persistent=true

[Install]
WantedBy=timers.target
EOF

# 5. 로그 정리 서비스
log_info "로그 정리 서비스 생성 중..."
cat > /etc/systemd/system/laravel-log-cleanup.service << EOF
[Unit]
Description=Laravel Log Cleanup
After=network.target

[Service]
Type=oneshot
User=$APP_USER
Group=$APP_USER
ExecStart=/bin/bash -c 'find $PROJECT_DIR/storage/logs -name "*.log" -mtime +30 -delete'
WorkingDirectory=$PROJECT_DIR
EOF

cat > /etc/systemd/system/laravel-log-cleanup.timer << EOF
[Unit]
Description=Run Laravel Log Cleanup daily
Requires=laravel-log-cleanup.service

[Timer]
OnCalendar=daily
Persistent=true

[Install]
WantedBy=timers.target
EOF

# 6. 캐시 워밍업 서비스
log_info "캐시 워밍업 서비스 생성 중..."
cat > /etc/systemd/system/laravel-cache-warmup.service << EOF
[Unit]
Description=Laravel Cache Warmup
After=network.target mariadb.service redis-server.service
Requires=mariadb.service redis-server.service

[Service]
Type=oneshot
User=$APP_USER
Group=$APP_USER
ExecStart=/bin/bash -c 'cd $PROJECT_DIR && php artisan config:cache && php artisan route:cache && php artisan view:cache'
WorkingDirectory=$PROJECT_DIR
Environment=LARAVEL_ENV=production
EOF

# 7. 시스템 모니터링 스크립트
log_info "시스템 모니터링 스크립트 생성 중..."
cat > /usr/local/bin/laravel-monitor.sh << 'EOF'
#!/bin/bash

PROJECT_DIR="/var/www/laravel-light-blog"
LOG_FILE="/var/log/laravel-monitor.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

# 로그 함수
log_message() {
    echo "[$DATE] $1" >> "$LOG_FILE"
}

# 디스크 사용량 확인 (90% 이상 시 경고)
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 90 ]; then
    log_message "WARNING: Disk usage is ${DISK_USAGE}%"
fi

# 메모리 사용량 확인
MEM_USAGE=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
if [ "$MEM_USAGE" -gt 90 ]; then
    log_message "WARNING: Memory usage is ${MEM_USAGE}%"
fi

# Laravel 로그 에러 확인
ERROR_COUNT=$(tail -100 "$PROJECT_DIR/storage/logs/laravel.log" 2>/dev/null | grep -c "ERROR" || echo 0)
if [ "$ERROR_COUNT" -gt 10 ]; then
    log_message "WARNING: High error count in Laravel logs: $ERROR_COUNT"
fi

# 서비스 상태 확인
SERVICES=("nginx" "mariadb" "redis-server" "php8.3-fpm")
for service in "${SERVICES[@]}"; do
    if ! systemctl is-active --quiet "$service"; then
        log_message "ERROR: Service $service is not running"
        systemctl start "$service"
    fi
done

log_message "INFO: System monitoring completed"
EOF

chmod +x /usr/local/bin/laravel-monitor.sh

# 8. 모니터링 서비스 및 타이머
log_info "모니터링 서비스 생성 중..."
cat > /etc/systemd/system/laravel-monitor.service << EOF
[Unit]
Description=Laravel System Monitor
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/laravel-monitor.sh
User=root
EOF

cat > /etc/systemd/system/laravel-monitor.timer << EOF
[Unit]
Description=Run Laravel System Monitor every 10 minutes
Requires=laravel-monitor.service

[Timer]
OnCalendar=*:0/10
Persistent=true

[Install]
WantedBy=timers.target
EOF

# 9. 백업 서비스
if [ -n "$DOMAIN" ]; then
    log_info "백업 서비스 생성 중..."
    cat > /etc/systemd/system/laravel-backup.service << EOF
[Unit]
Description=Laravel Application Backup
After=network.target mariadb.service

[Service]
Type=oneshot
User=root
ExecStart=/bin/bash -c '
    BACKUP_DIR="/var/backups/laravel-blog"
    DATE=\$(date +%Y%m%d_%H%M%S)
    mkdir -p \$BACKUP_DIR
    
    # 데이터베이스 백업
    mysqldump --single-transaction --routines --triggers laravel_blog > \$BACKUP_DIR/database_\$DATE.sql
    
    # 파일 백업 (storage 디렉토리)
    tar -czf \$BACKUP_DIR/storage_\$DATE.tar.gz -C $PROJECT_DIR storage
    
    # 30일 이상 된 백업 파일 삭제
    find \$BACKUP_DIR -name "*.sql" -mtime +30 -delete
    find \$BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
    
    echo "Backup completed at \$(date)" >> /var/log/laravel-backup.log
'
WorkingDirectory=$PROJECT_DIR
EOF

    cat > /etc/systemd/system/laravel-backup.timer << EOF
[Unit]
Description=Run Laravel Backup daily at 2 AM
Requires=laravel-backup.service

[Timer]
OnCalendar=*-*-* 02:00:00
Persistent=true

[Install]
WantedBy=timers.target
EOF
fi

# 10. systemd 데몬 재로드
log_info "systemd 설정 재로드 중..."
systemctl daemon-reload

# 11. 서비스 활성화 및 시작
log_info "서비스 활성화 및 시작 중..."

# 기본 서비스 활성화
systemctl enable nginx
systemctl enable mariadb
systemctl enable redis-server
systemctl enable php8.3-fpm

# Laravel 서비스 활성화 (Queue Worker는 선택사항)
systemctl enable laravel-scheduler.timer
systemctl enable laravel-healthcheck.timer
systemctl enable laravel-log-cleanup.timer
systemctl enable laravel-monitor.timer

if [ -n "$DOMAIN" ]; then
    systemctl enable laravel-backup.timer
fi

# 타이머 시작
systemctl start laravel-scheduler.timer
systemctl start laravel-healthcheck.timer
systemctl start laravel-log-cleanup.timer
systemctl start laravel-monitor.timer

if [ -n "$DOMAIN" ]; then
    systemctl start laravel-backup.timer
fi

# 캐시 워밍업 실행
systemctl start laravel-cache-warmup.service

# 12. 로그 디렉토리 생성
log_info "로그 디렉토리 설정 중..."
mkdir -p /var/log/laravel-blog
touch /var/log/laravel-monitor.log
touch /var/log/laravel-backup.log
chown $APP_USER:$APP_USER /var/log/laravel-blog
chmod 755 /var/log/laravel-blog

# 13. 서비스 상태 확인
log_info "서비스 상태 확인 중..."
echo ""
echo "=== 활성화된 타이머 ==="
systemctl list-timers laravel-*

echo ""
echo "=== 서비스 상태 ==="
for service in nginx mariadb redis-server php8.3-fpm; do
    if systemctl is-active --quiet "$service"; then
        log_success "$service: 실행 중"
    else
        log_error "$service: 중지됨"
    fi
done

# 완료 메시지
log_success "=========================================="
log_success "시스템 서비스 설정이 완료되었습니다!"
log_success "=========================================="
log_success ""
log_success "설정된 서비스:"
log_success "✅ Laravel Scheduler (매분 실행)"
log_success "✅ 헬스체크 (5분마다)"
log_success "✅ 로그 정리 (매일)"
log_success "✅ 시스템 모니터링 (10분마다)"
if [ -n "$DOMAIN" ]; then
    log_success "✅ 백업 (매일 새벽 2시)"
fi
log_success ""
log_info "상태 확인 명령어:"
echo "sudo systemctl list-timers laravel-*"
echo "sudo systemctl status laravel-scheduler.timer"
echo "sudo journalctl -u laravel-monitor.service"
echo "sudo tail -f /var/log/laravel-monitor.log"
if [ -n "$DOMAIN" ]; then
    echo "sudo tail -f /var/log/laravel-backup.log"
fi
log_success "=========================================="

# 선택 사항 안내
echo ""
log_info "선택 사항:"
echo "Queue Worker 시작: sudo systemctl enable --now laravel-queue.service"
echo "Horizon 시작: sudo systemctl enable --now laravel-horizon.service"
echo ""
log_warning "Queue Worker나 Horizon은 필요에 따라 수동으로 활성화하세요."