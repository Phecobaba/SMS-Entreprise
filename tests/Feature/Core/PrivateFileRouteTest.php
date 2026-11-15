<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PrivateFileRouteTest extends TestCase
{
    public function test_livewire_preview_requires_its_signed_bearer_url(): void
    {
        Storage::fake('tmp-for-tests');
        Storage::disk('tmp-for-tests')->put('livewire-tmp/preview.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2n8sAAAAASUVORK5CYII='
        ));
        $signed = URL::temporarySignedRoute('livewire.preview-file', now()->addMinute(),
            ['filename' => 'preview.png'], absolute: false);

        $this->get($signed)->assertOk();
        $this->get(route('livewire.preview-file', ['filename' => 'preview.png']))->assertUnauthorized();
    }

    public function test_local_private_storage_requires_its_signed_bearer_url(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/check.txt', 'private file');
        $signed = URL::temporarySignedRoute('storage.local', now()->addMinute(),
            ['path' => 'private/check.txt'], absolute: false);

        $this->get($signed)->assertOk();
        $this->get(route('storage.local', ['path' => 'private/check.txt']))->assertForbidden();
    }
}
