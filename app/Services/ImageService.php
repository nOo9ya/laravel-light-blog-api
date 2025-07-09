<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    private ImageManager $imageManager;
    private array $allowedMimeTypes;
    private int $maxFileSize;
    private int $defaultQuality;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB
        $this->defaultQuality = config('app.image_quality', 85);
    }

    public function uploadMainImage(UploadedFile $file, string $directory = 'posts/main'): array
    {
        $this->validateImage($file);
        
        // 디렉토리 경로 보안 검증
        $directory = $this->sanitizeDirectory($directory);
        
        $filename = $this->generateFilename($file, 'webp');
        $path = $directory . '/' . $filename;
        
        try {
            $image = $this->imageManager->read($file->getPathname());
            
            // 대표 이미지는 최대 1200px 너비로 리사이즈
            if ($image->width() > 1200) {
                $image->scale(width: 1200);
            }
            
            $webpData = $image->toWebp($this->defaultQuality);
            
            // 저장 전 디렉토리 생성 확인
            $this->ensureDirectoryExists($directory);
            $this->ensureDirectoryExists($directory . '/thumbs');
            
            Storage::put($path, $webpData);
            
            // 썸네일도 생성 (400px)
            $thumbnailPath = $directory . '/thumbs/' . $filename;
            $thumbnailImage = $image->scale(width: 400);
            $thumbnailWebpData = $thumbnailImage->toWebp($this->defaultQuality);
            Storage::put($thumbnailPath, $thumbnailWebpData);
            
            return [
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'original_name' => basename($file->getClientOriginalName()),
                'size' => strlen($webpData),
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('이미지 업로드 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    public function uploadOgImage(UploadedFile $file, string $directory = 'posts/og'): array
    {
        $this->validateImage($file);
        
        // 디렉토리 경로 보안 검증
        $directory = $this->sanitizeDirectory($directory);
        
        // OG 이미지는 1200x630 이상이어야 함
        $imageInfo = getimagesize($file->getPathname());
        if ($imageInfo[0] < 1200 || $imageInfo[1] < 630) {
            throw new \InvalidArgumentException('OG 이미지는 최소 1200x630 크기여야 합니다.');
        }
        
        $filename = $this->generateFilename($file, $file->getClientOriginalExtension());
        $path = $directory . '/' . $filename;
        
        try {
            $image = $this->imageManager->read($file->getPathname());
            
            // OG 이미지는 WebP 변환하지 않고 90% 품질로 압축
            $compressedData = match($file->getMimeType()) {
                'image/jpeg' => $image->toJpeg(90),
                'image/png' => $image->toPng(),
                'image/gif' => $image->toGif(),
                default => $image->toJpeg(90)
            };
            
            // 저장 전 디렉토리 생성 확인
            $this->ensureDirectoryExists($directory);
            
            Storage::put($path, $compressedData);
            
            return [
                'path' => $path,
                'original_name' => basename($file->getClientOriginalName()),
                'size' => strlen($compressedData),
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('OG 이미지 업로드 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    public function uploadContentImage(UploadedFile $file, string $directory = 'posts/content'): array
    {
        $this->validateImage($file);
        
        // 디렉토리 경로 보안 검증
        $directory = $this->sanitizeDirectory($directory);
        
        $filename = $this->generateFilename($file, 'webp');
        $path = $directory . '/' . $filename;
        
        try {
            $image = $this->imageManager->read($file->getPathname());
            
            // 본문 이미지는 최대 800px 너비로 리사이즈
            if ($image->width() > 800) {
                $image->scale(width: 800);
            }
            
            $webpData = $image->toWebp($this->defaultQuality);
            
            // 저장 전 디렉토리 생성 확인
            $this->ensureDirectoryExists($directory);
            
            Storage::put($path, $webpData);
            
            return [
                'path' => $path,
                'url' => Storage::url($path),
                'original_name' => basename($file->getClientOriginalName()),
                'size' => strlen($webpData),
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('본문 이미지 업로드 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    public function deleteImage(string $path): bool
    {
        if (Storage::exists($path)) {
            return Storage::delete($path);
        }
        
        return false;
    }

    public function deleteImageWithThumbnail(string $path): bool
    {
        $deleted = $this->deleteImage($path);
        
        // 썸네일도 삭제 시도
        $thumbnailPath = str_replace('/', '/thumbs/', $path);
        $this->deleteImage($thumbnailPath);
        
        return $deleted;
    }

    private function validateImage(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('유효하지 않은 파일입니다.');
        }
        
        // MIME 타입 검증 (더블 체크)
        $mimeType = $file->getMimeType();
        $detectedMimeType = mime_content_type($file->getPathname());
        
        if (!in_array($mimeType, $this->allowedMimeTypes) || 
            !in_array($detectedMimeType, $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('지원하지 않는 이미지 형식입니다. (JPG, PNG, GIF, WebP만 지원)');
        }
        
        // 파일 크기 검증
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('파일 크기가 너무 큽니다. (최대 10MB)');
        }
        
        // 파일 확장자 검증
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            throw new \InvalidArgumentException('허용되지 않는 파일 확장자입니다.');
        }
        
        // 이미지 파일 실제 검증
        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('유효하지 않은 이미지 파일입니다.');
        }
        
        // 이미지 크기 제한 (픽셀)
        $maxWidth = 5000;
        $maxHeight = 5000;
        if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
            throw new \InvalidArgumentException("이미지 크기가 너무 큽니다. (최대 {$maxWidth}x{$maxHeight}px)");
        }
        
        // 이미지 타입 검증
        $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!in_array($imageInfo[2], $allowedImageTypes)) {
            throw new \InvalidArgumentException('지원하지 않는 이미지 타입입니다.');
        }
    }

    private function generateFilename(UploadedFile $file, string $extension): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // 안전한 파일명 생성 (보안 강화)
        $name = preg_replace('/[^가-힣a-zA-Z0-9\-_]/', '', $name);
        $name = trim($name, '.-_');
        $name = substr($name, 0, 50); // 길이 제한
        $name = $name ?: 'image';
        
        // 확장자 정규화
        $extension = strtolower($extension);
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        
        // 안전한 파일명 생성
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return $name . '_' . $timestamp . '_' . $random . '.' . $extension;
    }

    public function getImageDimensions(string $path): array
    {
        if (!Storage::exists($path)) {
            return ['width' => 0, 'height' => 0];
        }
        
        $fullPath = Storage::path($path);
        $imageInfo = getimagesize($fullPath);
        
        return [
            'width' => $imageInfo[0] ?? 0,
            'height' => $imageInfo[1] ?? 0,
        ];
    }

    public function resizeImage(string $path, int $width, int $height = null): string
    {
        if (!Storage::exists($path)) {
            throw new \InvalidArgumentException('이미지 파일을 찾을 수 없습니다.');
        }
        
        $image = $this->imageManager->read(Storage::path($path));
        
        if ($height) {
            $image->resize($width, $height);
        } else {
            $image->scale(width: $width);
        }
        
        $resizedPath = str_replace('.', "_resized_{$width}x{$height}.", $path);
        $webpData = $image->toWebp($this->defaultQuality);
        Storage::put($resizedPath, $webpData);
        
        return $resizedPath;
    }

    /**
     * 디렉토리 경로 보안 검증
     */
    private function sanitizeDirectory(string $directory): string
    {
        // 경로 정규화 및 보안 검증
        $directory = trim($directory, '/\\');
        $directory = str_replace(['..', '\\'], '', $directory);
        $directory = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $directory);
        
        // 허용된 디렉토리 패턴 검증
        $allowedPatterns = [
            'posts/main',
            'posts/og', 
            'posts/content',
            'pages/main',
            'pages/content',
            'users/avatar'
        ];
        
        $isAllowed = false;
        foreach ($allowedPatterns as $pattern) {
            if (strpos($directory, $pattern) === 0) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            throw new \InvalidArgumentException('허용되지 않는 디렉토리 경로입니다.');
        }
        
        return $directory;
    }

    /**
     * 디렉토리 존재 확인 및 생성
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory, 0755, true);
        }
    }
}
