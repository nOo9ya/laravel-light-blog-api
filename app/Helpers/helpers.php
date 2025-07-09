<?php

if (!function_exists('themed')) {
    /**
     * 현재 테마에 맞는 뷰 경로 반환
     */
    function themed(string $view): string
    {
        $themeService = app(\App\Services\ThemeService::class);
        $currentTheme = $themeService->getCurrentTheme();
        
        return "themes.{$currentTheme}.{$view}";
    }
}

if (!function_exists('current_theme')) {
    /**
     * 현재 테마명 반환
     */
    function current_theme(): string
    {
        $themeService = app(\App\Services\ThemeService::class);
        return $themeService->getCurrentTheme();
    }
}