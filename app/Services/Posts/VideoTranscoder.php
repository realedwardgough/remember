<?php

declare(strict_types=1);

namespace App\Services\Posts;

use RuntimeException;
use Symfony\Component\Process\Process;

class VideoTranscoder
{
    /** @return array{width: int, height: int} */
    public function transcode(string $input, string $output): array
    {
        $process = new Process([
            config('video.ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-protocol_whitelist', 'file,pipe', '-i', $input,
            '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-map_chapters', '-1',
            '-vf', "scale=w='min(iw,1920)':h='min(ih,1080)':force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1",
            '-c:v', 'libx264', '-preset', 'medium', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-threads', '2', '-c:a', 'aac', '-b:a', '128k', '-ac', '2',
            '-movflags', '+faststart', '-f', 'mp4', $output,
        ]);
        $process->setTimeout(config('video.timeout') - 120);
        $process->mustRun();

        $probe = new Process([
            config('video.ffprobe'), '-v', 'error', '-show_streams', '-show_format', '-of', 'json', $output,
        ]);
        $probe->setTimeout(30)->mustRun();
        $info = json_decode($probe->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $video = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        $audio = collect($info['streams'] ?? [])->firstWhere('codec_type', 'audio');

        if (($video['codec_name'] ?? null) !== 'h264' || ($video['width'] ?? 0) < 2
            || ($video['height'] ?? 0) < 2 || $video['width'] > 1920 || $video['height'] > 1080
            || ($audio !== null && $audio['codec_name'] !== 'aac')
            || (float) ($info['format']['duration'] ?? 0) <= 0 || filesize($output) === 0) {
            throw new RuntimeException('Video output failed validation.');
        }

        return ['width' => $video['width'], 'height' => $video['height']];
    }
}
