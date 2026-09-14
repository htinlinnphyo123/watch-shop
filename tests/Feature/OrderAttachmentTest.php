<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class OrderAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->order = Order::create(['order_number' => 'FILES-'.uniqid(), 'total_amount' => 20000, 'status' => 'completed']);
        config(['filesystems.disks.order_attachments.bucket' => 'private-test-bucket']);
    }

    private function pending(array $attributes = []): OrderAttachment
    {
        $id = (string) Str::uuid();

        return OrderAttachment::create(array_merge([
            'id' => $id, 'order_id' => $this->order->id, 'user_id' => auth()->id(),
            'path' => "order-attachments/{$this->order->id}/{$id}.pdf", 'name' => 'Payment slip.pdf',
            'mime_type' => 'application/pdf', 'size' => 4, 'expires_at' => now()->addMinutes(10),
        ], $attributes));
    }

    public function test_presign_uses_generated_storage_key_and_only_accepts_metadata(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUploadUrl')->once()->withArgs(function ($path, $expiry, $options) {
            return str_starts_with($path, "order-attachments/{$this->order->id}/")
                && str_ends_with($path, '.pdf') && ! str_contains($path, 'slip')
                && $options['ContentType'] === 'application/pdf' && $options['ContentLength'] === 4
                && ! isset($options['ACL']);
        })->andReturn(['url' => 'https://storage.example.test/signed-put', 'headers' => ['Content-Type' => ['application/pdf']]]);
        Storage::shouldReceive('disk')->with('order_attachments')->andReturn($disk);
        Storage::shouldReceive('url')->with('')->andReturn('/storage');
        $response = $this->postJson(route('orders.files.presign', $this->order), ['name' => 'slip.pdf', 'size' => 4]);
        $response->assertOk()->assertJsonPath('content_type', 'application/pdf')->assertJsonMissingPath('path');
        $attachment = OrderAttachment::sole();
        $this->assertNull($attachment->uploaded_at);
        $this->assertSame(auth()->id(), $attachment->user_id);
    }

    public function test_multiple_completed_files_are_saved_and_visible_but_pending_files_are_not(): void
    {
        Storage::fake('order_attachments');
        $first = $this->pending();
        $second = $this->pending(['name' => 'Supporting document.pdf']);
        $this->pending();
        foreach ([$first, $second] as $file) {
            Storage::disk('order_attachments')->put($file->path, 'test');
            $this->postJson(route('orders.files.complete', [$this->order, $file]))->assertOk()->assertJsonMissingPath('path');
        }
        // Completion retries are idempotent.
        $this->postJson(route('orders.files.complete', [$this->order, $first]))->assertOk();
        $this->get(route('orders.show', $this->order))->assertInertia(fn (Assert $page) => $page
            ->has('order.file_uploads', 2)->missing('order.file_uploads.0.path'));
    }

    public function test_missing_or_wrong_size_uploads_cannot_be_completed(): void
    {
        Storage::fake('order_attachments');
        $file = $this->pending();
        $this->postJson(route('orders.files.complete', [$this->order, $file]))->assertUnprocessable();
        Storage::disk('order_attachments')->put($file->path, 'wrong-size');
        $this->postJson(route('orders.files.complete', [$this->order, $file]))->assertUnprocessable();
        $this->assertNull($file->fresh()->uploaded_at);
    }

    public function test_expired_wrong_order_and_other_users_uploads_are_rejected(): void
    {
        $file = $this->pending(['expires_at' => now()->subMinute()]);
        $this->postJson(route('orders.files.complete', [$this->order, $file]))->assertUnprocessable();
        $other = Order::create(['order_number' => 'OTHER', 'total_amount' => 1, 'status' => 'completed']);
        $this->postJson(route('orders.files.complete', [$other, $file]))->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->postJson(route('orders.files.complete', [$this->order, $file]))->assertForbidden();
    }

    public function test_invalid_files_and_order_limit_are_rejected(): void
    {
        $url = route('orders.files.presign', $this->order);
        $this->postJson($url, ['name' => 'script.html', 'size' => 4])->assertUnprocessable();
        $this->postJson($url, ['name' => 'slip.pdf', 'size' => 20971521])->assertUnprocessable();
        $this->postJson($url, ['name' => 'empty.pdf', 'size' => 0])->assertUnprocessable();
        for ($index = 0; $index < 20; $index++) {
            $this->pending();
        }
        $this->postJson($url, ['name' => 'slip.pdf', 'size' => 4])->assertUnprocessable();
    }

    public function test_download_requires_authentication_and_uses_a_temporary_attachment_url(): void
    {
        $file = $this->pending(['uploaded_at' => now()]);
        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')->once()->withArgs(fn ($path, $expiry, $options) => $path === $file->path && $options['ResponseContentType'] === 'application/octet-stream'
            && str_starts_with($options['ResponseContentDisposition'], 'attachment;'))
            ->andReturn('https://storage.example.test/signed-download');
        Storage::shouldReceive('disk')->with('order_attachments')->andReturn($disk);
        Storage::shouldReceive('url')->with('')->andReturn('/storage');
        $this->get(route('orders.files.download', [$this->order, $file]))->assertRedirect('https://storage.example.test/signed-download');
        $pending = $this->pending();
        $this->get(route('orders.files.download', [$this->order, $pending]))->assertNotFound();
        auth()->logout();
        $this->get(route('orders.files.download', [$this->order, $file]))->assertRedirect('/login');
        $this->postJson(route('orders.files.presign', $this->order), ['name' => 'slip.pdf', 'size' => 4])->assertUnauthorized();
    }
}
