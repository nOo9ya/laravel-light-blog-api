#!/bin/bash

# ========================================
# Laravel Light Blog API 통합 배포 스크립트
# ========================================
# 
# 이 스크립트는 모든 배포 관련 작업을 자동화합니다:
# 1. 환경 검증 및 사전 준비
# 2. Docker 환경 또는 베어메탈 환경 선택 배포
# 3. 필요한 초기화 스크립트 실행
# 4. 사후 검증 및 상태 확인
#
# 사용법: ./deploy-all.sh [옵션]
# ========================================

set -e

# 컬러 출력 설정
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

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

log_step() {
    echo -e "${PURPLE}[STEP]${NC} $1"
}

log_substep() {
    echo -e "${CYAN}  └─${NC} $1"
}

# 배너 출력
print_banner() {
    clear
    echo -e "${PURPLE}"
    echo "========================================"
    echo "  Laravel Light Blog API 통합 배포     "
    echo "========================================"
    echo -e "${NC}"
    echo "🚀 자동화된 배포 프로세스를 시작합니다"
    echo ""
}

# 사용법 출력
usage() {
    echo "사용법: $0 [배포방식] [옵션]"
    echo ""
    echo "배포 방식:"
    echo "  docker              Docker 컨테이너 배포"
    echo "  baremetal           베어메탈 서버 배포"
    echo "  systemd             SystemD 서비스 배포"
    echo "  ssl-only            SSL 인증서만 설정 (기존 서버)"
    echo "  auto                자동 감지 (기본값)"
    echo ""
    echo "Docker 배포 옵션:"
    echo "  --build             이미지 강제 재빌드"
    echo "  --init-dirs         디렉토리 초기화"
    echo "  --backup            기존 데이터 백업"
    echo ""
    echo "베어메탈 배포 옵션:"
    echo "  --domain DOMAIN     도메인명 (필수)"
    echo "  --email EMAIL       SSL 인증서용 이메일 (필수)"
    echo "  --db-password PASS  데이터베이스 비밀번호 (필수)"
    echo "  --project-dir DIR   프로젝트 경로 (기본: /var/www/laravel-light-blog-api)"
    echo ""
    echo "공통 옵션:"
    echo "  --skip-verify       사전 검증 건너뛰기"
    echo "  --quiet             최소 출력"
    echo "  --help              도움말 출력"
    echo ""
    echo "예시:"
    echo "  $0 docker --build --init-dirs"
    echo "  $0 baremetal --domain example.com --email admin@example.com --db-password secret123"
    echo "  $0 systemd --project-dir /opt/blog-api"
    echo "  $0 ssl-only --domain example.com --email admin@example.com"
    echo ""
    exit 0
}

# 변수 초기화
DEPLOY_MODE="auto"
FORCE_BUILD=false
INIT_DIRS=false
BACKUP_DATA=false
SKIP_VERIFY=false
QUIET=false

# 베어메탈 배포 변수
DOMAIN=""
EMAIL=""
DB_PASSWORD=""
PROJECT_DIR="/var/www/laravel-light-blog-api"

# 매개변수 파싱
while [[ $# -gt 0 ]]; do
    case $1 in
        docker|baremetal|systemd|ssl-only|auto)
            DEPLOY_MODE="$1"
            shift
            ;;
        --build)
            FORCE_BUILD=true
            shift
            ;;
        --init-dirs)
            INIT_DIRS=true
            shift
            ;;
        --backup)
            BACKUP_DATA=true
            shift
            ;;
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
        --skip-verify)
            SKIP_VERIFY=true
            shift
            ;;
        --quiet)
            QUIET=true
            shift
            ;;
        --help)
            usage
            ;;
        *)
            log_error "알 수 없는 옵션: $1"
            usage
            ;;
    esac
done

# 환경 자동 감지
detect_environment() {
    if [ "$DEPLOY_MODE" != "auto" ]; then
        return
    fi

    log_step "배포 환경 자동 감지 중..."
    
    if command -v docker &> /dev/null && command -v docker-compose &> /dev/null; then
        DEPLOY_MODE="docker"
        log_substep "Docker 환경 감지됨"
    elif systemctl --version &> /dev/null; then
        DEPLOY_MODE="systemd"
        log_substep "SystemD 환경 감지됨"
    else
        DEPLOY_MODE="baremetal"
        log_substep "베어메탈 환경으로 설정됨"
    fi
}

# 사전 검증
verify_prerequisites() {
    if [ "$SKIP_VERIFY" = true ]; then
        log_warning "사전 검증을 건너뜁니다"
        return
    fi

    log_step "사전 검증 수행 중..."

    # 권한 확인
    if [ "$DEPLOY_MODE" = "baremetal" ] || [ "$DEPLOY_MODE" = "systemd" ]; then
        if [ "$EUID" -eq 0 ]; then
            log_error "베어메탈/SystemD 배포는 root 권한으로 실행하지 마세요"
            log_info "일반 사용자로 실행하면 필요시 sudo를 요청합니다"
            exit 1
        fi
    fi

    # 필수 파라미터 확인
    if [ "$DEPLOY_MODE" = "baremetal" ]; then
        if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ] || [ -z "$DB_PASSWORD" ]; then
            log_error "베어메탈 배포에는 --domain, --email, --db-password가 필수입니다"
            exit 1
        fi
    elif [ "$DEPLOY_MODE" = "ssl-only" ]; then
        if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ]; then
            log_error "SSL 설정에는 --domain, --email이 필수입니다"
            exit 1
        fi
    fi

    # 파일 존재 확인
    local required_files=(
        "docker-compose.production.yml"
        ".docker/Dockerfile.production"
        "deploy.sh"
    )

    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            log_error "필수 파일이 없습니다: $file"
            exit 1
        fi
    done

    log_substep "모든 사전 검증을 통과했습니다"
}

# Docker 배포
deploy_docker() {
    log_step "Docker 환경 배포 시작..."

    # 1. 디렉토리 초기화
    if [ "$INIT_DIRS" = true ]; then
        log_substep "호스트 디렉토리 초기화..."
        if [ -f ".docker/scripts/init-directories.sh" ]; then
            ./.docker/scripts/init-directories.sh
        else
            log_warning "init-directories.sh를 찾을 수 없습니다"
        fi
    fi

    # 2. 기존 데이터 백업
    if [ "$BACKUP_DATA" = true ]; then
        log_substep "기존 데이터 백업 중..."
        if [ -f ".docker/scripts/backup-volumes.sh" ]; then
            ./.docker/scripts/backup-volumes.sh
        else
            log_warning "backup-volumes.sh를 찾을 수 없습니다"
        fi
    fi

    # 3. 이미지 빌드 최적화
    if [ "$FORCE_BUILD" = true ]; then
        log_substep "Docker 이미지 최적화 빌드 중..."
        if [ -f ".docker/scripts/build-optimize.sh" ]; then
            ./.docker/scripts/build-optimize.sh
        else
            log_warning "build-optimize.sh를 찾을 수 없습니다"
        fi
    fi

    # 4. Docker Compose 실행
    log_substep "Docker Compose 서비스 시작 중..."
    if [ "$FORCE_BUILD" = true ]; then
        docker-compose -f docker-compose.production.yml up -d --build
    else
        docker-compose -f docker-compose.production.yml up -d
    fi

    # 5. 컨테이너 내부 cron 설정
    log_substep "컨테이너 cron 설정 중..."
    if docker ps | grep -q "laravel_blog_app"; then
        if [ -f ".docker/scripts/setup-cron.sh" ]; then
            docker cp .docker/scripts/setup-cron.sh laravel_blog_app:/tmp/
            docker exec laravel_blog_app bash /tmp/setup-cron.sh
        fi
    fi

    log_success "Docker 배포 완료!"
}

# 베어메탈 배포
deploy_baremetal() {
    log_step "베어메탈 서버 배포 시작..."

    if [ ! -f "deploy.sh" ]; then
        log_error "deploy.sh 파일을 찾을 수 없습니다"
        exit 1
    fi

    log_substep "베어메탈 배포 스크립트 실행 중..."
    ./deploy.sh \
        --domain "$DOMAIN" \
        --email "$EMAIL" \
        --db-password "$DB_PASSWORD" \
        --project-dir "$PROJECT_DIR"

    log_success "베어메탈 배포 완료!"
}

# SSL 전용 설정 (독립 실행)
setup_ssl_only() {
    log_step "SSL 인증서 전용 설정 시작..."

    if [ ! -f "ssl-setup.sh" ]; then
        log_error "ssl-setup.sh 파일을 찾을 수 없습니다"
        exit 1
    fi

    if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ]; then
        log_error "SSL 설정에는 --domain과 --email이 필수입니다"
        exit 1
    fi

    log_substep "SSL 설정 스크립트 실행 중..."
    sudo ./ssl-setup.sh \
        --domain "$DOMAIN" \
        --email "$EMAIL"

    log_success "SSL 인증서 설정 완료!"
}

# SystemD 배포
deploy_systemd() {
    log_step "SystemD 서비스 배포 시작..."

    if [ ! -f "system-service.sh" ]; then
        log_error "system-service.sh 파일을 찾을 수 없습니다"
        exit 1
    fi

    log_substep "SystemD 서비스 등록 중..."
    sudo ./system-service.sh \
        --project-dir "$PROJECT_DIR" \
        --app-user "www-data"

    log_success "SystemD 배포 완료!"
}

# 배포 후 검증
post_deployment_verification() {
    log_step "배포 후 검증 수행 중..."

    case $DEPLOY_MODE in
        "docker")
            log_substep "Docker 컨테이너 상태 확인..."
            docker-compose -f docker-compose.production.yml ps
            
            log_substep "Laravel 애플리케이션 상태 확인..."
            if docker ps | grep -q "laravel_blog_app"; then
                docker exec laravel_blog_app php artisan --version
                docker exec laravel_blog_app php artisan schedule:list
            fi
            ;;
        "baremetal")
            log_substep "시스템 서비스 상태 확인..."
            systemctl is-active nginx php8.3-fpm mariadb redis-server --quiet && log_substep "모든 서비스가 실행 중입니다"
            
            log_substep "Laravel 애플리케이션 상태 확인..."
            cd "$PROJECT_DIR" && php artisan --version
            ;;
        "systemd")
            log_substep "SystemD 서비스 상태 확인..."
            systemctl is-active laravel-queue-worker --quiet && log_substep "Laravel Queue Worker 실행 중"
            ;;
    esac

    log_success "배포 후 검증 완료!"
}

# 배포 정보 출력
print_deployment_info() {
    log_step "배포 정보 요약"

    echo ""
    echo "📋 배포 정보:"
    echo "   배포 방식: $DEPLOY_MODE"
    echo "   프로젝트 경로: $PROJECT_DIR"
    
    if [ "$DEPLOY_MODE" = "baremetal" ] && [ -n "$DOMAIN" ]; then
        echo "   도메인: $DOMAIN"
        echo "   API Base URL: https://$DOMAIN/api/v1"
    fi

    echo ""
    echo "🔍 상태 확인 명령어:"
    case $DEPLOY_MODE in
        "docker")
            echo "   docker-compose -f docker-compose.production.yml ps"
            echo "   docker exec laravel_blog_app php artisan schedule:list"
            echo "   docker logs laravel_blog_app"
            ;;
        "baremetal")
            echo "   systemctl status nginx php8.3-fpm mariadb"
            echo "   tail -f $PROJECT_DIR/storage/logs/laravel.log"
            echo "   crontab -l"
            ;;
        "systemd")
            echo "   systemctl list-timers laravel-*"
            echo "   systemctl status laravel-queue-worker"
            echo "   journalctl -u laravel-monitor.service"
            ;;
    esac

    echo ""
    echo "📁 중요 파일 위치:"
    case $DEPLOY_MODE in
        "docker")
            echo "   로그: .logs/ 디렉토리"
            echo "   데이터베이스: .database/ 디렉토리"
            echo "   SSL 인증서: .ssl/ 디렉토리"
            ;;
        "baremetal")
            echo "   로그: $PROJECT_DIR/storage/logs/"
            echo "   Nginx 설정: /etc/nginx/sites-available/laravel-blog-api"
            echo "   SSL 인증서: /etc/letsencrypt/"
            ;;
    esac

    echo ""
}

# 메인 실행 흐름
main() {
    print_banner

    # 환경 감지
    detect_environment

    log_info "배포 방식: $DEPLOY_MODE"
    echo ""

    # 사전 검증
    verify_prerequisites

    # 사용자 확인
    if [ "$QUIET" != true ]; then
        echo ""
        log_warning "배포를 시작하시겠습니까? (계속하려면 Enter, 취소하려면 Ctrl+C)"
        read -r
    fi

    # 배포 시작 시간 기록
    START_TIME=$(date +%s)

    # 배포 실행
    case $DEPLOY_MODE in
        "docker")
            deploy_docker
            ;;
        "baremetal")
            deploy_baremetal
            ;;
        "systemd")
            deploy_systemd
            ;;
        "ssl-only")
            setup_ssl_only
            ;;
        *)
            log_error "지원하지 않는 배포 방식: $DEPLOY_MODE"
            exit 1
            ;;
    esac

    # 배포 후 검증
    post_deployment_verification

    # 배포 완료 시간 계산
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))

    # 최종 결과 출력
    echo ""
    log_success "=========================================="
    log_success "🎉 배포가 성공적으로 완료되었습니다!"
    log_success "소요 시간: ${DURATION}초"
    log_success "=========================================="

    print_deployment_info
}

# 스크립트 실행
main "$@"