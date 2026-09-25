<?php

namespace Tests\Feature;

use App\Enums\FieldType;
use App\Models\RemoteService;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('remote_services.async', false);
        config()->set('remote_services.enforce_dns_resolution', false);
        config()->set('remote_services.token', 'image-test-token');
    }

    public function test_image_is_encoded_as_raw_base64_and_can_be_rerun_or_replaced(): void
    {
        Http::fake(['https://api.example.test/*' => Http::response(['ok' => true], 200)]);
        [$user, $service] = $this->makeAssignedImageService();
        $firstImage = UploadedFile::fake()->image('photo.png', 12, 12);
        $firstEncoded = base64_encode($firstImage->getContent());

        $this->actingAs($user)->get(route('services.show', $service))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('type="file"', false);

        $this->actingAs($user)->post(route('requests.store', $service), [
            'input' => ['photo' => $firstImage],
        ])->assertRedirect();

        $request = ServiceRequest::query()->firstOrFail();
        $this->assertSame('succeeded', $request->status->value);
        $this->assertSame($firstEncoded, $request->input_payload['photo']);
        $this->assertSame($firstEncoded, $request->attempts()->firstOrFail()->request_payload['photo']);
        $this->assertStringNotContainsString($firstEncoded, DB::table('service_requests')->where('id', $request->id)->value('input_payload'));
        Http::assertSent(fn ($sent) => json_decode($sent->body(), true)['photo'] === $firstEncoded);

        $this->actingAs($user)->get(route('requests.show', $request))->assertOk()->assertDontSee($firstEncoded);
        $this->actingAs($user)->get(route('requests.attempts.show', [$request, $request->attempts()->firstOrFail()]))->assertOk()->assertDontSee($firstEncoded);

        $this->actingAs($user)->post(route('requests.refresh', $request))->assertRedirect();
        $this->assertSame($firstEncoded, $request->fresh()->input_payload['photo']);
        Http::assertSentCount(2);

        $secondImage = UploadedFile::fake()->image('replacement.jpg', 16, 16);
        $secondEncoded = base64_encode($secondImage->getContent());
        $this->actingAs($user)->post(route('requests.update', $request), [
            '_method' => 'PUT',
            'input' => ['photo' => $secondImage],
        ])->assertRedirect();

        $this->assertSame($secondEncoded, $request->fresh()->input_payload['photo']);
        $this->assertSame(3, $request->fresh()->attempt_count);
        Http::assertSentCount(3);
    }

    public function test_non_image_and_oversized_image_are_rejected_before_request_creation(): void
    {
        Http::fake();
        [$user, $service] = $this->makeAssignedImageService();

        $this->actingAs($user)->post(route('requests.store', $service), [
            'input' => ['photo' => UploadedFile::fake()->create('note.txt', 1, 'text/plain')],
        ])->assertSessionHasErrors('photo');

        $this->actingAs($user)->post(route('requests.store', $service), [
            'input' => ['photo' => UploadedFile::fake()->image('large.png')->size(2049)],
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('service_requests', 0);
        Http::assertNothingSent();
    }

    public function test_total_image_size_is_limited_before_encoding(): void
    {
        Http::fake();
        [$user, $service] = $this->makeAssignedImageService();
        foreach (['back', 'selfie'] as $key) {
            $service->fields()->create([
                'direction' => 'input',
                'key' => $key,
                'label' => $key,
                'type' => FieldType::Image,
                'is_required' => true,
            ]);
        }

        $this->actingAs($user)->post(route('requests.store', $service), [
            'input' => [
                'photo' => UploadedFile::fake()->image('front.png')->size(1500),
                'back' => UploadedFile::fake()->image('back.png')->size(1500),
                'selfie' => UploadedFile::fake()->image('selfie.png')->size(1500),
            ],
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('service_requests', 0);
        Http::assertNothingSent();
    }

    public function test_image_stays_masked_in_history_and_edit_form_after_field_changes(): void
    {
        Http::fake(['https://api.example.test/*' => Http::response(['ok' => true], 200)]);
        [$user, $service] = $this->makeAssignedImageService();
        $image = UploadedFile::fake()->image('private.png', 12, 12);
        $encoded = base64_encode($image->getContent());

        $this->actingAs($user)->post(route('requests.store', $service), [
            'input' => ['photo' => $image],
        ])->assertRedirect();

        $request = ServiceRequest::query()->firstOrFail();
        $attempt = $request->attempts()->firstOrFail();
        $this->assertSame(['photo'], $request->sensitive_input_keys);
        $this->assertSame(['photo'], $attempt->sensitive_input_keys);

        $service->fields()->where('key', 'photo')->update(['type' => FieldType::Text->value, 'is_sensitive' => false]);
        $this->actingAs($user)->get(route('requests.show', $request))->assertOk()->assertDontSee($encoded);
        $this->actingAs($user)->get(route('requests.attempts.show', [$request, $attempt]))->assertOk()->assertDontSee($encoded);

        $this->actingAs($user)->put(route('requests.update', $request), [
            'input' => ['photo' => 'replacement'],
        ])->assertRedirect();
        $this->assertSame([], $request->fresh()->sensitive_input_keys);
        $this->assertSame(['photo'], $attempt->fresh()->sensitive_input_keys);
        $this->actingAs($user)->get(route('requests.attempts.show', [$request, $attempt]))->assertOk()->assertDontSee($encoded);

        $service->fields()->where('key', 'photo')->delete();
        $this->actingAs($user)->get(route('requests.show', $request))->assertOk()->assertDontSee($encoded);
        $this->actingAs($user)->get(route('requests.attempts.show', [$request, $attempt]))->assertOk()->assertDontSee($encoded);
    }

    private function makeAssignedImageService(): array
    {
        $user = User::factory()->create();
        $service = RemoteService::query()->create([
            'name' => 'Image Inquiry',
            'slug' => 'image-inquiry',
            'endpoint_url' => 'https://api.example.test/inquiry',
            'headers' => ['Accept' => 'application/json'],
        ]);
        $service->fields()->create([
            'direction' => 'input',
            'key' => 'photo',
            'label' => 'تصویر',
            'type' => FieldType::Image,
            'is_required' => true,
            'is_sensitive' => true,
        ]);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);

        return [$user, $service];
    }
}
