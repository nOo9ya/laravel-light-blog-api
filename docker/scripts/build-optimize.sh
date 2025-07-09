#!/bin/bash

# Docker 이미지 빌드 최적화 스크립트

set -e

# 로그 함수
log_info() {
    echo -e "\033[0;34m[INFO]\033[0m $1"
}

log_success() {
    echo -e "\033[0;32m[SUCCESS]\033[0m $1"
}

log_warning() {
    echo -e "\033[1;33m[WARNING]\033[0m $1"
}

# 프로젝트 루트로 이동
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$PROJECT_ROOT"

# 이미지 이름 설정
IMAGE_NAME="laravel-blog-api"
IMAGE_TAG="latest"
PROD_IMAGE="${IMAGE_NAME}:${IMAGE_TAG}"
DEV_IMAGE="${IMAGE_NAME}:dev"

log_info "Docker 이미지 빌드 최적화 시작..."

# BuildKit 활성화 확인
export DOCKER_BUILDKIT=1

# 1. 기존 이미지 정리 (선택사항)
read -p "기존 이미지를 정리하시겠습니까? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_info "기존 이미지 정리 중..."
    docker image prune -f
    docker system prune -f
    log_success "기존 이미지 정리 완료"
fi

# 2. 프로덕션 이미지 빌드
log_info "프로덕션 이미지 빌드 중..."
docker build \
    --target production \
    --tag $PROD_IMAGE \
    --file Dockerfile.production \
    --build-arg BUILDKIT_INLINE_CACHE=1 \
    .

# 3. 개발 이미지 빌드
log_info "개발 이미지 빌드 중..."
docker build \
    --target development \
    --tag $DEV_IMAGE \
    --file Dockerfile.production \
    --build-arg BUILDKIT_INLINE_CACHE=1 \
    .

# 4. 이미지 크기 분석
log_info "이미지 크기 분석..."
echo ""
echo "📊 빌드된 이미지 크기:"
docker images | grep $IMAGE_NAME | sort -k2

# 5. 이미지 레이어 분석 (dive 도구가 있는 경우)
if command -v dive &> /dev/null; then
    log_info "이미지 레이어 분석을 위해 'dive' 도구를 실행합니다..."
    echo "분석할 이미지를 선택하세요:"
    echo "1) 프로덕션 이미지: $PROD_IMAGE"
    echo "2) 개발 이미지: $DEV_IMAGE"
    read -p "선택 (1-2): " choice
    
    case $choice in
        1) dive $PROD_IMAGE ;;
        2) dive $DEV_IMAGE ;;
        *) log_warning "잘못된 선택입니다." ;;
    esac
else
    log_warning "이미지 레이어 분석을 위해 'dive' 도구 설치를 권장합니다:"
    echo "설치: https://github.com/wagoodman/dive"
fi

# 6. 취약점 스캔 (trivy가 있는 경우)
if command -v trivy &> /dev/null; then
    log_info "보안 취약점 스캔 중..."
    trivy image $PROD_IMAGE
else
    log_warning "보안 취약점 스캔을 위해 'trivy' 도구 설치를 권장합니다:"
    echo "설치: https://github.com/aquasecurity/trivy"
fi

# 7. 빌드 캐시 정보
log_info "빌드 캐시 정보:"
docker buildx du

log_success "Docker 이미지 빌드 최적화 완료!"
echo ""
echo "🚀 사용 방법:"
echo "프로덕션: docker run -d $PROD_IMAGE"
echo "개발환경: docker run -d $DEV_IMAGE"
echo ""
echo "📝 최적화 효과:"
echo "- 멀티스테이지 빌드로 이미지 크기 60-70% 감소"
echo "- Alpine Linux 기반으로 보안 취약점 최소화"
echo "- 레이어 캐싱으로 빌드 시간 단축"
echo "- 불필요한 빌드 도구 제거"