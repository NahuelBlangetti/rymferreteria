<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportFile;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireStaleProductImportsTest extends TestCase
{
    use RefreshDatabase;

    private function import(User $user, string $status, \DateTimeInterface $updatedAt): ProductImport
    {
        $import = ProductImport::create([
            'user_id' => $user->id,
            'filename' => 'lista.xlsx',
            'file_path' => 'imports/lista.xlsx',
            'status' => $status,
        ]);

        $import->forceFill(['updated_at' => $updatedAt, 'created_at' => $updatedAt])->save();

        return $import->fresh();
    }

    public function test_expire_stale_marks_old_pending_and_processing_imports_as_error(): void
    {
        $user = User::factory()->create();

        $stalePending = $this->import($user, 'pending', now()->subMinutes(20));
        $staleProcessing = $this->import($user, 'processing', now()->subMinutes(20));
        $freshPending = $this->import($user, 'pending', now()->subMinutes(2));
        $done = $this->import($user, 'done', now()->subDays(7));

        $this->assertSame(2, ProductImport::expireStale(15));

        $this->assertSame('error', $stalePending->fresh()->status);
        $this->assertSame('error', $staleProcessing->fresh()->status);
        $this->assertSame('pending', $freshPending->fresh()->status);
        $this->assertSame('done', $done->fresh()->status);
    }

    public function test_artisan_command_expires_stale_imports(): void
    {
        $user = User::factory()->create();
        $import = $this->import($user, 'pending', now()->subHour());

        $this->artisan('imports:expire-stale')
            ->assertSuccessful();

        $this->assertSame('error', $import->fresh()->status);
    }

    public function test_job_skips_imports_that_are_no_longer_active(): void
    {
        $user = User::factory()->create();
        $import = $this->import($user, 'cancelled', now());

        (new ProcessImportFile($import->id))->handle();

        $this->assertSame('cancelled', $import->fresh()->status);
    }
}
