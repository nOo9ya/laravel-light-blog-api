<?php

namespace Database\Factories;

use App\Models\PostAttachment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostAttachment>
 */
class PostAttachmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PostAttachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileTypes = [
            'pdf' => [
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size_min' => 100000,
                'size_max' => 5000000,
            ],
            'doc' => [
                'extension' => 'docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'size_min' => 50000,
                'size_max' => 2000000,
            ],
            'xls' => [
                'extension' => 'xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'size_min' => 30000,
                'size_max' => 1500000,
            ],
            'ppt' => [
                'extension' => 'pptx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'size_min' => 200000,
                'size_max' => 10000000,
            ],
            'zip' => [
                'extension' => 'zip',
                'mime_type' => 'application/zip',
                'size_min' => 500000,
                'size_max' => 50000000,
            ],
            'txt' => [
                'extension' => 'txt',
                'mime_type' => 'text/plain',
                'size_min' => 1000,
                'size_max' => 100000,
            ],
        ];

        $fileType = $this->faker->randomElement($fileTypes);
        $originalName = $this->faker->word() . '.' . $fileType['extension'];
        $fileName = $this->faker->uuid() . '.' . $fileType['extension'];

        return [
            'post_id' => Post::factory(),
            'original_name' => $originalName,
            'file_name' => $fileName,
            'file_path' => 'attachments/' . date('Y/m/') . $fileName,
            'file_size' => $this->faker->numberBetween($fileType['size_min'], $fileType['size_max']),
            'mime_type' => $fileType['mime_type'],
            'extension' => $fileType['extension'],
            'download_count' => $this->faker->numberBetween(0, 100),
            'is_public' => $this->faker->boolean(80),
            'description' => $this->faker->optional(0.7)->sentence(),
            'uploaded_by' => null, // Post의 user_id와 동일하게 설정됨
        ];
    }

    /**
     * PDF 파일
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => $this->faker->word() . '.pdf',
            'file_name' => $this->faker->uuid() . '.pdf',
            'file_path' => 'attachments/' . date('Y/m/') . $this->faker->uuid() . '.pdf',
            'file_size' => $this->faker->numberBetween(100000, 5000000),
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
        ]);
    }

    /**
     * Word 문서
     */
    public function word(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => $this->faker->word() . '.docx',
            'file_name' => $this->faker->uuid() . '.docx',
            'file_path' => 'attachments/' . date('Y/m/') . $this->faker->uuid() . '.docx',
            'file_size' => $this->faker->numberBetween(50000, 2000000),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'extension' => 'docx',
        ]);
    }

    /**
     * Excel 스프레드시트
     */
    public function excel(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => $this->faker->word() . '.xlsx',
            'file_name' => $this->faker->uuid() . '.xlsx',
            'file_path' => 'attachments/' . date('Y/m/') . $this->faker->uuid() . '.xlsx',
            'file_size' => $this->faker->numberBetween(30000, 1500000),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'extension' => 'xlsx',
        ]);
    }

    /**
     * PowerPoint 프레젠테이션
     */
    public function powerpoint(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => $this->faker->word() . '.pptx',
            'file_name' => $this->faker->uuid() . '.pptx',
            'file_path' => 'attachments/' . date('Y/m/') . $this->faker->uuid() . '.pptx',
            'file_size' => $this->faker->numberBetween(200000, 10000000),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'extension' => 'pptx',
        ]);
    }

    /**
     * ZIP 압축 파일
     */
    public function zip(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => $this->faker->word() . '.zip',
            'file_name' => $this->faker->uuid() . '.zip',
            'file_path' => 'attachments/' . date('Y/m/') . $this->faker->uuid() . '.zip',
            'file_size' => $this->faker->numberBetween(500000, 50000000),
            'mime_type' => 'application/zip',
            'extension' => 'zip',
        ]);
    }

    /**
     * 텍스트 파일
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => $this->faker->word() . '.txt',
            'file_name' => $this->faker->uuid() . '.txt',
            'file_path' => 'attachments/' . date('Y/m/') . $this->faker->uuid() . '.txt',
            'file_size' => $this->faker->numberBetween(1000, 100000),
            'mime_type' => 'text/plain',
            'extension' => 'txt',
        ]);
    }

    /**
     * 공개 파일
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * 비공개 파일
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * 인기 파일 (다운로드 수 많음)
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'download_count' => $this->faker->numberBetween(100, 1000),
        ]);
    }

    /**
     * 새 파일 (다운로드 수 적음)
     */
    public function fresh(): static
    {
        return $this->state(fn (array $attributes) => [
            'download_count' => $this->faker->numberBetween(0, 10),
        ]);
    }

    /**
     * 큰 파일
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(10000000, 100000000), // 10MB - 100MB
        ]);
    }

    /**
     * 작은 파일
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(1000, 500000), // 1KB - 500KB
        ]);
    }

    /**
     * 설명이 있는 파일
     */
    public function withDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $this->faker->sentence(10),
        ]);
    }
}