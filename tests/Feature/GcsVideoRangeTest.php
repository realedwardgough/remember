<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\GoogleCloudStorage\GoogleCloudStorageAdapter;
use Tests\TestCase;

class GcsVideoRangeTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function video_requests_fetch_only_normalized_bytes_from_the_prefixed_gcs_object(): void
    {
        $post = Post::factory()->memory()->create();
        $media = Media::factory()->for($post)->create([
            'disk' => 'gcs', 'path' => 'video.mp4', 'mime_type' => 'video/mp4', 'size' => 100000000,
            'metadata' => ['status' => 'ready'],
        ]);
        $disk = Mockery::mock(GoogleCloudStorageAdapter::class);
        $client = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);
        $object = Mockery::mock(StorageObject::class);
        Storage::shouldReceive('disk')->with('gcs')->twice()->andReturn($disk);
        $disk->shouldReceive('getConfig')->twice()->andReturn(['bucket' => 'private-media', 'root' => 'family']);
        $disk->shouldReceive('getClient')->twice()->andReturn($client);
        $disk->shouldNotReceive('readStream');
        $client->shouldReceive('bucket')->with('private-media')->twice()->andReturn($bucket);
        $bucket->shouldReceive('object')->with('family/video.mp4')->twice()->andReturn($object);
        $object->shouldReceive('downloadAsStream')->with(['restOptions' => [
            'stream' => true, 'headers' => ['Range' => 'bytes=99999996-99999999'],
        ]])->twice()->andReturnUsing(static fn () => Utils::streamFor('tail'));

        foreach (['bytes=99999996-', 'bytes=-4'] as $range) {
            $this->actingAs($post->author)->get(route('media.show', $media), ['Range' => $range])
                ->assertStatus(206)->assertHeader('Content-Range', 'bytes 99999996-99999999/100000000')
                ->assertHeader('Content-Length', '4')->assertStreamedContent('tail');
        }
        $this->head(route('media.show', $media), ['Range' => 'bytes=-4'])->assertStatus(206)->assertContent('');
        $this->get(route('media.show', $media), ['Range' => 'bytes=100000000-'])->assertStatus(416);
        $post->delete();
        $this->get(route('media.show', $media), ['Range' => 'bytes=-4'])->assertNotFound();
    }
}
