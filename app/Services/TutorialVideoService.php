<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tutorial videos for the "How To Do ?" pages. The videos live in the Spaces bucket
 * under <root>/tutorial videos/<Role>/ (e.g. BD, Designer) and are played straight
 * from their public links, so adding a video to that folder is all it takes to
 * show it on the site (the list refreshes within LIST_TTL_SECONDS).
 */
class TutorialVideoService
{
    public const ROLE_FOLDERS = ['bd' => 'BD', 'designer' => 'Designer'];

    private const LIST_TTL_SECONDS = 600;

    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];

    /**
     * @return array{videos: array<int, array{title: string, file: string, url: string, size_bytes: int}>, error: bool}
     */
    public function forRole(string $role): array
    {
        $folder = self::ROLE_FOLDERS[$role] ?? null;

        if ($folder === null) {
            return ['videos' => [], 'error' => false];
        }

        $key = 'tutorial-videos:'.$folder;

        if (is_array($cached = Cache::get($key))) {
            return ['videos' => $cached, 'error' => false];
        }

        try {
            $disk = Storage::disk('spaces');
            $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
            $directory = $root.'/tutorial videos/'.$folder;
            $base = rtrim($disk->url(''), '/');

            $videos = collect($disk->files($directory))
                ->filter(fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS, true))
                ->map(fn (string $path) => [
                    'title' => $this->titleFor(basename($path), $folder),
                    'file' => basename($path),
                    // Encode each segment: the folder name has a space in it.
                    'url' => $base.'/'.implode('/', array_map('rawurlencode', explode('/', $path))),
                    'size_bytes' => (int) $disk->size($path),
                ])
                ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        } catch (Throwable $e) {
            // A storage hiccup must not take the page down, and must not be cached.
            Log::warning('Tutorial video list failed', ['role' => $role, 'error' => $e->getMessage()]);

            return ['videos' => [], 'error' => true];
        }

        Cache::put($key, $videos, self::LIST_TTL_SECONDS);

        return ['videos' => $videos, 'error' => false];
    }

    /** "BD_Events_3D_Layout_Tutorial.mp4" → "Events 3D Layout". */
    private function titleFor(string $filename, string $folder): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/^'.preg_quote($folder, '/').'[_\s-]+/i', '', $name);
        $name = preg_replace('/(^|[_\s-])Tutorial($|[_\s-])/i', ' ', $name);
        $name = trim(preg_replace('/[_\s]+/', ' ', $name));

        return $name !== '' ? Str::of($name)->toString() : $filename;
    }
}
