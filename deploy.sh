#!/bin/bash

# Laravel Light Blog API 배포 스크립트
# API 전용 서비스 - AWS Lightsail 및 Ubuntu 환경 기준

set -e  # 오류 발생시 스크립트 중단

# 컬러 출력 설정
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 로그 함수
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

# 사용자 확인
if [ "$EUID" -eq 0 ]; then
    log_error "이 스크립트는 root 권한으로 실행하지 마세요."
    exit 1
fi

# 환경 변수 설정
DOMAIN=""
EMAIL=""
APP_ENV="production"
DB_HOST="localhost"
DB_DATABASE="laravel_blog_api"
DB_USERNAME="blog_api_user"
DB_PASSWORD=""
PROJECT_DIR="/var/www/laravel-light-blog-api"
NGINX_CONF="/etc/nginx/sites-available/laravel-blog-api"

# 매개변수 파싱
while [[ $# -gt 0 ]]; do
    case $1 in
        --domain)
            DOMAIN="$2"
            shift 2
            ;;
        --email)
            EMAIL="$2"
            shift 2
            ;;
        --db-password)
            DB_PASSWORD="$2"
            shift 2
            ;;
        --project-dir)
            PROJECT_DIR="$2"
            shift 2
            ;;
        --help)
            echo "사용법: $0 --domain example.com --email admin@example.com --db-password password"
            echo "옵션:"
            echo "  --domain        도메인명 (필수)"
            echo "  --email         Let's Encrypt 인증서용 이메일 (필수)"
            echo "  --db-password   데이터베이스 비밀번호 (필수)"
            echo "  --project-dir   프로젝트 설치 경로 (기본: /var/www/laravel-light-blog-api)"
            exit 0
            ;;
        *)
            log_error "알 수 없는 옵션: $1"
            exit 1
            ;;
    esac
done

# 필수 매개변수 확인
if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ] || [ -z "$DB_PASSWORD" ]; then
    log_error "필수 매개변수가 누락되었습니다."
    echo "사용법: $0 --domain example.com --email admin@example.com --db-password password"
    exit 1
fi

log_info "Laravel Light Blog API 배포를 시작합니다..."
log_info "도메인: $DOMAIN"
log_info "이메일: $EMAIL"
log_info "프로젝트 경로: $PROJECT_DIR"

# 1. 시스템 업데이트 및 필수 패키지 설치
log_info "시스템 업데이트 및 필수 패키지 설치 중..."
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mariadb-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml \
    php8.3-mbstring php8.3-curl php8.3-zip php8.3-gd php8.3-imagick php8.3-redis \
    redis-server git curl wget unzip certbot python3-certbot-nginx composer

# 2. MariaDB 설정
log_info "MariaDB 설정 중..."
sudo systemctl start mariadb
sudo systemctl enable mariadb

# MariaDB 성능 최적화 및 slow query 로그 설정
sudo tee /etc/mysql/mariadb.conf.d/99-blog-api.cnf > /dev/null <<EOF
[mysqld]
# 성능 최적화 (AWS Lightsail 1GB RAM 기준)
innodb_buffer_pool_size = 512M
innodb_log_file_size = 128M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# 연결 설정
max_connections = 100
thread_cache_size = 16
table_open_cache = 400

# Slow Query 로그 설정
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2
log_queries_not_using_indexes = 1
min_examined_row_limit = 100

# 일반 쿼리 로그 (필요시 활성화)
# general_log = 1
# general_log_file = /var/log/mysql/mysql.log

# 바이너리 로그 설정
log_bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 7
max_binlog_size = 100M

# 문자셋 설정
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci

# 타임존 설정
default_time_zone = '+09:00'
EOF

# MySQL 로그 디렉토리 생성 및 권한 설정
sudo mkdir -p /var/log/mysql
sudo chown mysql:mysql /var/log/mysql
sudo chmod 750 /var/log/mysql

# MariaDB 재시작
sudo systemctl restart mariadb

# 데이터베이스 생성
sudo mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_DATABASE};"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ${DB_DATABASE}.* TO '${DB_USERNAME}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# 3. 프로젝트 디렉토리 생성 및 권한 설정
log_info "프로젝트 디렉토리 생성 중..."
sudo mkdir -p $PROJECT_DIR
sudo chown -R $USER:www-data $PROJECT_DIR
sudo chmod -R 755 $PROJECT_DIR

# 4. 프로젝트 파일 복사
log_info "프로젝트 파일 복사 중..."
if [ "$(pwd)" != "$PROJECT_DIR" ]; then
    sudo cp -R . $PROJECT_DIR/
    sudo chown -R $USER:www-data $PROJECT_DIR
fi

cd $PROJECT_DIR

# 5. Composer 의존성 설치
log_info "Composer 의존성 설치 중..."
composer install --optimize-autoloader --no-dev

# 6. 환경 파일 설정
log_info "환경 파일 설정 중..."
if [ ! -f .env ]; then
    cp .env.example .env
fi

# .env 파일 업데이트
sed -i "s/APP_ENV=.*/APP_ENV=${APP_ENV}/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s/APP_URL=.*/APP_URL=https:\/\/${DOMAIN}/" .env
sed -i "s/DB_HOST=.*/DB_HOST=${DB_HOST}/" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE}/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME}/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" .env

# 앱 키 생성
php artisan key:generate --force

# JWT 시크릿 생성
php artisan jwt:secret --force

# 7. 데이터베이스 마이그레이션 및 시딩
log_info "데이터베이스 마이그레이션 실행 중..."
php artisan migrate --force
php artisan db:seed --force

# 8. 스토리지 링크 생성
log_info "스토리지 링크 생성 중..."
php artisan storage:link

# 9. 캐시 최적화
log_info "캐시 최적화 중..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 10. 권한 설정
log_info "파일 권한 설정 중..."
sudo chown -R $USER:www-data $PROJECT_DIR
sudo chmod -R 755 $PROJECT_DIR
sudo chmod -R 775 $PROJECT_DIR/storage
sudo chmod -R 775 $PROJECT_DIR/bootstrap/cache

# 11. Nginx API 전용 설정
log_info "Nginx API 전용 설정 중..."
sudo tee $NGINX_CONF > /dev/null <<EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $PROJECT_DIR/public;
    index index.php;

    # API 전용 보안 헤더
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'none'; script-src 'self'; connect-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline';" always;

    # CORS 헤더 (기본값, Laravel에서 처리)
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin" always;

    # Gzip compression for API responses
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types application/json application/javascript text/css text/xml text/plain text/javascript;

    # API Rate limiting
    limit_req_zone \$binary_remote_addr zone=api:10m rate=60r/m;
    limit_req_zone \$binary_remote_addr zone=auth:10m rate=10r/m;
    limit_req_zone \$binary_remote_addr zone=admin:10m rate=30r/m;

    # API 라우팅
    location /api/ {
        limit_req zone=api burst=100 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # 인증 API 제한
    location ~ ^/api/v1/auth/(login|register) {
        limit_req zone=auth burst=5 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # 관리자 API 제한
    location ~ ^/api/v1/admin/ {
        limit_req zone=admin burst=20 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # 헬스체크 및 시스템 정보 (제한 없음)
    location ~ ^/api/v1/system/ {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # 기본 라우팅
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP 처리
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # 업로드된 파일 접근 (storage/app/public 링크)
    location /storage/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # 민감한 파일 접근 차단
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ /(vendor|bootstrap|storage/app|storage/framework|storage/logs)/ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # .env 파일 보호
    location ~ /\.env {
        deny all;
        access_log off;
        log_not_found off;
    }
}
EOF

# Nginx 사이트 활성화
sudo ln -sf $NGINX_CONF /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# 12. PHP-FPM 최적화
log_info "PHP-FPM 최적화 중..."
sudo tee /etc/php/8.3/fpm/pool.d/www.conf > /dev/null <<EOF
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 1000
request_terminate_timeout = 300
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 25M
php_admin_value[max_execution_time] = 300
EOF

# PHP OPcache 설정
sudo tee /etc/php/8.3/fpm/conf.d/99-opcache.ini > /dev/null <<EOF
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
EOF

sudo systemctl restart php8.3-fpm

# 13. Redis 설정
log_info "Redis 설정 중..."
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Redis 설정 파일 최적화
sudo tee -a /etc/redis/redis.conf > /dev/null <<EOF
maxmemory 64mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
EOF

sudo systemctl restart redis-server

# 14. 시스템 서비스 등록
log_info "시스템 서비스 설정 중..."
sudo systemctl enable nginx
sudo systemctl enable mariadb
sudo systemctl enable php8.3-fpm
sudo systemctl enable redis-server

# 15. 방화벽 설정
log_info "방화벽 설정 중..."
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable

# 16. SSL 인증서 설치 (Let's Encrypt)
log_info "SSL 인증서 설치 중..."
sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN --email $EMAIL --agree-tos --non-interactive --redirect

# SSL 인증서 자동 갱신 설정 (30일에 한번 새벽 5시)
log_info "SSL 인증서 자동 갱신 설정 중..."
sudo crontab -l 2>/dev/null | grep -q "certbot renew" || (sudo crontab -l 2>/dev/null; echo "0 5 */30 * * /usr/bin/certbot renew --quiet --post-hook 'systemctl reload nginx'") | sudo crontab -

# 17. 크론탭 설정 (Laravel 스케줄러)
log_info "크론탭 설정 중..."
(crontab -l 2>/dev/null; echo "* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# 18. 로그 로테이션 설정 (1일 단위, 180일 보관)
log_info "로그 로테이션 설정 중..."

# Laravel 로그 로테이션 (180일 보관)
sudo tee /etc/logrotate.d/laravel-blog-api > /dev/null <<EOF
$PROJECT_DIR/storage/logs/*.log {
    daily
    missingok
    rotate 180
    compress
    delaycompress
    notifempty
    create 644 $USER www-data
    postrotate
        sudo systemctl reload php8.3-fpm
    endscript
}
EOF

# Nginx 로그 로테이션 (180일 보관)
sudo tee /etc/logrotate.d/nginx-blog-api > /dev/null <<EOF
/var/log/nginx/access.log
/var/log/nginx/error.log {
    daily
    missingok
    rotate 180
    compress
    delaycompress
    notifempty
    create 644 www-data adm
    sharedscripts
    postrotate
        if [ -f /var/run/nginx.pid ]; then
            kill -USR1 \`cat /var/run/nginx.pid\`
        fi
    endscript
}
EOF

# Supervisor 로그 로테이션 (180일 보관)
sudo tee /etc/logrotate.d/supervisor-blog-api > /dev/null <<EOF
/var/log/supervisor/*.log {
    daily
    missingok
    rotate 180
    compress
    delaycompress
    notifempty
    create 644 root root
    postrotate
        /usr/bin/supervisorctl reread
        /usr/bin/supervisorctl update
    endscript
}
EOF

# MariaDB 로그 로테이션 (180일 보관)
sudo tee /etc/logrotate.d/mariadb-blog-api > /dev/null <<EOF
/var/log/mysql/mysql-slow.log {
    daily
    missingok
    rotate 180
    compress
    delaycompress
    notifempty
    create 640 mysql mysql
    postrotate
        /usr/bin/mysqladmin flush-logs
    endscript
}

/var/log/mysql/mysql-bin.* {
    daily
    missingok
    rotate 180
    compress
    delaycompress
    notifempty
    create 640 mysql mysql
    postrotate
        /usr/bin/mysqladmin flush-logs
    endscript
}
EOF

# 오래된 로그 파일 자동 정리 스크립트 생성
sudo tee /usr/local/bin/cleanup-old-logs.sh > /dev/null <<'EOF'
#!/bin/bash
# 180일 이상 된 로그 파일 자동 삭제 스크립트

LOG_DIRS=(
    "/var/log/nginx"
    "/var/log/supervisor"
    "/var/log/mysql"
    "$PROJECT_DIR/storage/logs"
)

for LOG_DIR in "${LOG_DIRS[@]}"; do
    if [ -d "$LOG_DIR" ]; then
        echo "Cleaning up logs in $LOG_DIR..."
        find "$LOG_DIR" -type f -name "*.log*" -mtime +180 -exec rm -f {} \;
        find "$LOG_DIR" -type f -name "*.gz" -mtime +180 -exec rm -f {} \;
    fi
done

echo "Log cleanup completed at $(date)"
EOF

# 스크립트 실행 권한 부여
sudo chmod +x /usr/local/bin/cleanup-old-logs.sh

# 로그 정리 cron 작업 추가 (매일 새벽 2시)
sudo crontab -l 2>/dev/null | grep -q "cleanup-old-logs" || (sudo crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/cleanup-old-logs.sh >> /var/log/log-cleanup.log 2>&1") | sudo crontab -

log_success "로그 로테이션 및 자동 정리 설정이 완료되었습니다."
log_info "- Laravel, Nginx, Supervisor, MariaDB 로그 모두 1일 단위로 로테이션됩니다."
log_info "- 180일 이상 된 로그 파일은 매일 새벽 2시에 자동으로 삭제됩니다."

# 19. 스왑 파일 설정 (메모리 부족 방지 - 리부팅 후에도 유지)
log_info "스왑 파일 설정 중..."
if [ ! -f /swapfile ]; then
    log_info "2GB 스왑 파일 생성 중..."
    sudo fallocate -l 2G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    
    # fstab에 등록 (리부팅 후에도 자동 마운트)
    if ! grep -q '/swapfile' /etc/fstab; then
        echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
        log_success "스왑 파일이 /etc/fstab에 등록되었습니다 (리부팅 후에도 유지)"
    fi
    
    # 스왑 설정 최적화
    echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf
    echo 'vm.vfs_cache_pressure=50' | sudo tee -a /etc/sysctl.conf
    sudo sysctl vm.swappiness=10
    sudo sysctl vm.vfs_cache_pressure=50
    
    log_success "2GB 스왑 파일이 생성되고 최적화되었습니다"
else
    log_info "스왑 파일이 이미 존재합니다"
    
    # 기존 스왑 파일이 fstab에 등록되어 있는지 확인
    if ! grep -q '/swapfile' /etc/fstab; then
        echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
        log_success "기존 스왑 파일이 /etc/fstab에 등록되었습니다"
    fi
fi

# 현재 스왑 상태 확인
log_info "현재 스왑 상태:"
sudo swapon --show

# 20. 최종 API 테스트
log_info "API 배포 테스트 중..."
cd $PROJECT_DIR

# 애플리케이션 테스트
if php artisan --version > /dev/null 2>&1; then
    log_success "Laravel API 애플리케이션이 정상적으로 설치되었습니다."
else
    log_error "Laravel API 애플리케이션 설치에 실패했습니다."
    exit 1
fi

# API 헬스체크 테스트
sleep 5  # 서비스 시작 대기
if curl -s -o /dev/null -w "%{http_code}" http://localhost/api/v1/system/health | grep -q "200"; then
    log_success "API 서버가 정상적으로 작동합니다."
else
    log_warning "API 헬스체크에 실패했습니다. 수동으로 확인해주세요."
fi

# JWT 설정 확인
if php artisan jwt:check > /dev/null 2>&1; then
    log_success "JWT 설정이 정상적으로 구성되었습니다."
else
    log_warning "JWT 설정을 확인해주세요."
fi

# 완료 메시지
log_success "============================================="
log_success "Laravel Light Blog API 배포가 완료되었습니다!"
log_success "============================================="
log_success "API Base URL: https://$DOMAIN/api/v1"
log_success "헬스체크: https://$DOMAIN/api/v1/system/health"
log_success "시스템 정보: https://$DOMAIN/api/v1/system/info"
log_success ""
log_success "API 테스트 명령어:"
log_success "curl https://$DOMAIN/api/v1/system/health"
log_success ""
log_success "관리자 계정 생성:"
log_success "php artisan make:admin {name} {email} {password}"
log_success ""
log_warning "JWT 시크릿과 데이터베이스 정보를 안전하게 보관하세요!"
log_success "============================================="

# 상태 확인 명령어 안내
echo ""
log_info "시스템 상태 확인 명령어:"
echo "sudo systemctl status nginx"
echo "sudo systemctl status php8.3-fpm"
echo "sudo systemctl status mariadb"
echo "sudo systemctl status redis-server"
echo ""
log_info "로그 확인 명령어:"
echo "sudo tail -f /var/log/nginx/error.log"
echo "sudo tail -f $PROJECT_DIR/storage/logs/laravel.log"