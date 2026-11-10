<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Posts\VideoTranscoder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VideoTranscoderTest extends TestCase
{
    #[Test]
    public function real_ffmpeg_produces_playable_mp4s_without_enlarging_small_videos(): void
    {
        $finder = new ExecutableFinder;
        if ($finder->find(config('video.ffmpeg')) === null || $finder->find(config('video.ffprobe')) === null) {
            $this->markTestSkipped('FFmpeg and ffprobe are required for the conversion integration test.');
        }
        $directory = storage_path('framework/testing/video-'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        try {
            foreach ([[64, 48, true], [2000, 1126, false], [48, 64, false]] as [$width, $height, $audio]) {
                $input = $directory.'/input.mov';
                $output = $directory.'/output.mp4';
                $arguments = [config('video.ffmpeg'), '-y', '-v', 'error', '-f', 'lavfi',
                    '-i', "color=c=blue:s={$width}x{$height}:r=10:d=0.2"];
                if ($audio) {
                    array_push($arguments, '-f', 'lavfi', '-i', 'sine=frequency=440:duration=0.2', '-c:a', 'pcm_s16le');
                }
                array_push($arguments, '-c:v', 'mpeg4', '-threads', '2', $input);
                (new Process($arguments))->setTimeout(30)->mustRun();
                $dimensions = app(VideoTranscoder::class)->transcode($input, $output);
                $this->assertLessThanOrEqual(min($width, 1920), $dimensions['width']);
                $this->assertLessThanOrEqual(min($height, 1080), $dimensions['height']);
                $this->assertEqualsWithDelta($width / $height, $dimensions['width'] / $dimensions['height'], 0.02);
                $this->assertGreaterThan(0, filesize($output));
                $thumbnail = $directory.'/thumbnail.jpg';
                app(VideoTranscoder::class)->thumbnail($output, $thumbnail);
                $image = getimagesize($thumbnail);
                $this->assertSame(IMAGETYPE_JPEG, $image[2]);
                $this->assertLessThanOrEqual(960, max($image[0], $image[1]));
                $bytes = file_get_contents($output);
                $this->assertLessThan(strpos($bytes, 'mdat'), strpos($bytes, 'moov'));
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
