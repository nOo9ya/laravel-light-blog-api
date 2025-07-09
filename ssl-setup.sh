#!/bin/bash

# Let's Encrypt SSL 인증서 자동화 스크립트 (독립 실행용)
# Laravel Light Blog용
#
# ⚠️  주의: 이 스크립트는 독립적으로 SSL만 설정할 때 사용됩니다.
# 📋  전체 배포: deploy-all.sh 또는 deploy.sh 사용 권장
# 🔧  SSL만 설정: 이 스크립트 사용

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

# 사용자 확인
if [ "$EUID" -ne 0 ]; then
    log_error "이 스크립트는 root 권한으로 실행해야 합니다."
    echo "sudo $0 으로 실행해주세요."
    exit 1
fi

# 환경 변수 설정
DOMAIN=""
EMAIL=""
WEBROOT="/var/www/laravel-light-blog/public"
NGINX_CONF="/etc/nginx/sites-available/laravel-blog"

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
        --webroot)
            WEBROOT="$2"
            shift 2
            ;;
        --nginx-conf)
            NGINX_CONF="$2"
            shift 2
            ;;
        --renew)
            certbot renew --quiet
            systemctl reload nginx
            log_success "SSL 인증서가 갱신되었습니다."
            exit 0
            ;;
        --test-renewal)
            certbot renew --dry-run
            log_success "SSL 인증서 갱신 테스트가 성공했습니다."
            exit 0
            ;;
        --help)
            echo "사용법: $0 [옵션]"
            echo ""
            echo "SSL 인증서 발급:"
            echo "  sudo $0 --domain example.com --email admin@example.com"
            echo ""
            echo "옵션:"
            echo "  --domain        도메인명 (필수)"
            echo "  --email         Let's Encrypt 계정 이메일 (필수)"
            echo "  --webroot       웹루트 경로 (기본: /var/www/laravel-light-blog/public)"
            echo "  --nginx-conf    Nginx 설정 파일 (기본: /etc/nginx/sites-available/laravel-blog)"
            echo ""
            echo "SSL 인증서 관리:"
            echo "  sudo $0 --renew           SSL 인증서 수동 갱신"
            echo "  sudo $0 --test-renewal    SSL 인증서 갱신 테스트"
            echo ""
            echo "예제:"
            echo "  sudo $0 --domain myblog.com --email admin@myblog.com"
            exit 0
            ;;
        *)
            log_error "알 수 없는 옵션: $1"
            exit 1
            ;;
    esac
done

# 새 인증서 발급 시 필수 매개변수 확인
if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ]; then
    log_error "도메인과 이메일이 필요합니다."
    echo "사용법: sudo $0 --domain example.com --email admin@example.com"
    exit 1
fi

log_info "Let's Encrypt SSL 인증서 설정을 시작합니다..."
log_info "도메인: $DOMAIN"
log_info "이메일: $EMAIL"

# 1. Certbot 설치 확인
if ! command -v certbot &> /dev/null; then
    log_info "Certbot 설치 중..."
    apt update
    apt install -y certbot python3-certbot-nginx
fi

# 2. 방화벽 설정 확인
log_info "방화벽 설정 확인 중..."
ufw allow 'Nginx Full'
ufw allow 22/tcp

# 3. Nginx 설정 백업
log_info "Nginx 설정 백업 중..."
if [ -f "$NGINX_CONF" ]; then
    cp "$NGINX_CONF" "${NGINX_CONF}.backup.$(date +%Y%m%d_%H%M%S)"
    log_success "Nginx 설정이 백업되었습니다."
fi

# 4. 도메인 DNS 확인
log_info "도메인 DNS 확인 중..."
if ! nslookup "$DOMAIN" > /dev/null 2>&1; then
    log_warning "도메인 $DOMAIN의 DNS 설정을 확인할 수 없습니다."
    log_warning "도메인이 이 서버의 IP를 올바르게 가리키는지 확인해주세요."
    read -p "계속 진행하시겠습니까? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# 5. 임시 Nginx 설정 (HTTP만)
log_info "임시 HTTP 설정 생성 중..."
cat > "$NGINX_CONF" << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $WEBROOT;
    index index.php index.html;

    # Let's Encrypt 검증을 위한 설정
    location /.well-known/acme-challenge/ {
        root $WEBROOT;
        allow all;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

# Nginx 설정 테스트 및 재시작
nginx -t
systemctl reload nginx

# 6. SSL 인증서 발급
log_info "SSL 인증서 발급 중..."
certbot certonly \
    --webroot \
    --webroot-path="$WEBROOT" \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    --domains "$DOMAIN,www.$DOMAIN"

# 7. 완전한 Nginx 설정 생성 (HTTPS 포함)
log_info "HTTPS Nginx 설정 생성 중..."
cat > "$NGINX_CONF" << EOF
# HTTP에서 HTTPS로 리다이렉트
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    return 301 https://\$server_name\$request_uri;
}

# HTTPS 서버 설정
server {
    listen 443 ssl http2;
    server_name $DOMAIN www.$DOMAIN;
    root $WEBROOT;
    index index.php index.html;

    # SSL 인증서
    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    
    # SSL 보안 설정
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # 보안 헤더
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    
    # Gzip 압축
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Rate limiting
    limit_req_zone \$binary_remote_addr zone=login:10m rate=10r/m;
    limit_req_zone \$binary_remote_addr zone=global:10m rate=30r/m;

    location / {
        limit_req zone=global burst=20 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ ^/(login|register|password) {
        limit_req zone=login burst=5 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTPS on;
        fastcgi_param HTTP_SCHEME https;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # 정적 파일 캐싱
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp|svg|woff|woff2|ttf|eot)$ {
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

    location ~ /(vendor|storage|bootstrap)/ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # 클라이언트 업로드 크기 제한
    client_max_body_size 25M;
    
    # 로그 설정
    access_log /var/log/nginx/laravel-blog-access.log;
    error_log /var/log/nginx/laravel-blog-error.log;
}
EOF

# 8. Nginx 설정 테스트 및 재시작
log_info "Nginx 설정 테스트 중..."
nginx -t
systemctl reload nginx

# 9. 자동 갱신 설정
log_info "SSL 인증서 자동 갱신 설정 중..."

# systemd 타이머를 사용한 자동 갱신
cat > /etc/systemd/system/certbot-renewal.service << EOF
[Unit]
Description=Certbot Renewal
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/bin/certbot renew --quiet --deploy-hook "systemctl reload nginx"
User=root
EOF

cat > /etc/systemd/system/certbot-renewal.timer << EOF
[Unit]
Description=Run certbot twice daily
Requires=certbot-renewal.service

[Timer]
OnCalendar=*-*-* 00,12:00:00
RandomizedDelaySec=3600
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable certbot-renewal.timer
systemctl start certbot-renewal.timer

# 10. Certbot 갱신 훅 스크립트 생성
log_info "갱신 훅 스크립트 생성 중..."
mkdir -p /etc/letsencrypt/renewal-hooks/deploy
cat > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh << 'EOF'
#!/bin/bash
systemctl reload nginx
echo "$(date): SSL certificate renewed and Nginx reloaded" >> /var/log/certbot-renewal.log
EOF

chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh

# 11. HTTPS 리다이렉트 테스트
log_info "HTTPS 설정 테스트 중..."
sleep 2

if curl -s -I -L http://"$DOMAIN" | grep -q "HTTP/2 200"; then
    log_success "HTTPS 리다이렉트가 정상적으로 작동합니다."
elif curl -s -I https://"$DOMAIN" | grep -q "HTTP/2 200\|200 OK"; then
    log_success "HTTPS가 정상적으로 작동합니다."
else
    log_warning "HTTPS 설정을 수동으로 확인해주세요."
fi

# 12. SSL 인증서 정보 출력
log_info "SSL 인증서 정보:"
certbot certificates

# 완료 메시지
log_success "=========================================="
log_success "Let's Encrypt SSL 인증서 설정이 완료되었습니다!"
log_success "=========================================="
log_success "도메인: https://$DOMAIN"
log_success "WWW: https://www.$DOMAIN"
log_success ""
log_success "SSL 등급 확인: https://www.ssllabs.com/ssltest/analyze.html?d=$DOMAIN"
log_success ""
log_info "자동 갱신 상태 확인: systemctl status certbot-renewal.timer"
log_info "수동 갱신 테스트: sudo certbot renew --dry-run"
log_info "인증서 정보 확인: sudo certbot certificates"
log_success "=========================================="

# SSL 보안 등급 향상을 위한 추가 권장사항
echo ""
log_info "추가 보안 강화 권장사항:"
echo "1. OCSP Stapling 활성화 (Nginx에서 ssl_stapling on; 설정)"
echo "2. HTTP/3 지원 (Nginx 1.25+ 필요)"
echo "3. Certificate Transparency 모니터링"
echo "4. SSL 등급 A+ 달성을 위한 추가 설정"
echo ""
log_warning "인증서는 90일마다 자동으로 갱신됩니다."
log_warning "갱신 실패 시 이메일($EMAIL)로 알림이 전송됩니다."