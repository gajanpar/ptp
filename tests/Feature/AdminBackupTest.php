<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminBackupTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $penilaiUser;

    protected BackupService $backupService;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('username', 'admin')->firstOrFail();
        $this->penilaiUser = User::where('username', 'rudy')->firstOrFail();
        $this->backupService = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        // Clean test backup files created during test runs
        $files = File::files(storage_path('app/backups'));
        foreach ($files as $file) {
            if (str_starts_with($file->getFilename(), 'spktad_cirebon_backup_')) {
                File::delete($file->getRealPath());
            }
        }

        parent::tearDown();
    }

    public function test_admin_can_view_backup_console(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/system/backup');

        $response->assertStatus(200);
        $response->assertSee('Cadangan &amp; Pemulihan Basis Data', false);
        $response->assertSee('Buat Cadangan Baru');
        $response->assertSee('Prosedur Pemulihan Bencana');
    }

    public function test_admin_can_trigger_backup_creation_and_activity_is_logged(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/system/backup');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $backups = $this->backupService->listBackups();
        $this->assertNotEmpty($backups);

        $latest = $backups[0];
        $this->assertFileExists($this->backupService->getBackupDirectory().DIRECTORY_SEPARATOR.$latest['filename']);
        $this->assertGreaterThan(0, $latest['size']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system.backup_created',
            'user_name' => $this->adminUser->name,
        ]);
    }

    public function test_admin_can_download_backup(): void
    {
        $backup = $this->backupService->createBackup();

        $response = $this->actingAs($this->adminUser)->get("/admin/system/backup/download/{$backup['filename']}");

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', "attachment; filename={$backup['filename']}");

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system.backup_downloaded',
            'user_name' => $this->adminUser->name,
        ]);
    }

    public function test_path_traversal_attack_is_blocked_on_download_and_delete(): void
    {
        // Malicious traversal attempts via HTTP
        $traversalAttempts = [
            '..%2F..%2Fdatabase.sqlite',
            '..%5C..%5Cdatabase.sqlite',
            '../../.env',
            '../database/database.sqlite',
            'non-existent-backup.sqlite',
        ];

        foreach ($traversalAttempts as $payload) {
            $downloadResponse = $this->actingAs($this->adminUser)->get("/admin/system/backup/download/{$payload}");
            $this->assertTrue(in_array($downloadResponse->getStatusCode(), [404, 403]));

            $deleteResponse = $this->actingAs($this->adminUser)->delete("/admin/system/backup/{$payload}");
            $this->assertTrue(in_array($deleteResponse->getStatusCode(), [302, 404, 403]));
            if ($deleteResponse->getStatusCode() === 302) {
                $deleteResponse->assertSessionHas('error');
            }
        }

        // Direct Service-level validation for path traversal with raw backslashes and slashes
        $directPayloads = [
            '..\\..\\database.sqlite',
            '../../.env',
            '../database/database.sqlite',
            'subfolder/backup.sqlite',
        ];

        foreach ($directPayloads as $badPayload) {
            try {
                $this->backupService->getBackupPath($badPayload);
                $this->fail("Expected InvalidArgumentException for traversal payload: {$badPayload}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Permintaan akses file tidak valid', $e->getMessage());
            }
        }
    }

    public function test_admin_can_delete_backup(): void
    {
        $backup = $this->backupService->createBackup();
        $filename = $backup['filename'];

        $response = $this->actingAs($this->adminUser)->delete("/admin/system/backup/{$filename}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFileDoesNotExist($this->backupService->getBackupDirectory().DIRECTORY_SEPARATOR.$filename);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system.backup_deleted',
            'user_name' => $this->adminUser->name,
        ]);
    }

    public function test_non_admin_cannot_access_or_trigger_backup(): void
    {
        $endpoints = [
            ['method' => 'GET', 'uri' => '/admin/system/backup'],
            ['method' => 'POST', 'uri' => '/admin/system/backup'],
        ];

        foreach ($endpoints as $ep) {
            $response = $this->actingAs($this->penilaiUser)->call($ep['method'], $ep['uri']);
            $response->assertRedirect('/dashboard');
            $response->assertSessionHas('error');
        }
    }

    public function test_artisan_backup_command(): void
    {
        $code = Artisan::call('ptp:backup', ['--clean' => true]);

        $this->assertEquals(0, $code);

        $backups = $this->backupService->listBackups();
        $this->assertNotEmpty($backups);
    }
}
