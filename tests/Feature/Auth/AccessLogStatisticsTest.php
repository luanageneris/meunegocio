<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

function createAccessAudit(User $user, string $action, string $date): void
{
    $log = new AuditLog([
        'company_id' => $user->company_id,
        'user_id' => $user->id,
        'action' => $action,
        'model' => User::class,
        'model_id' => $user->id,
        'ip_address' => '127.0.0.1',
    ]);
    $log->created_at = Carbon::parse($date);
    $log->updated_at = $log->created_at;
    $log->save();
}

test('statistics include every day and month, including zeros and the current periods', function () {
    Carbon::setTestNow('2026-03-01 12:00:00');
    $master = User::factory()->root()->create(['email' => 'admin@meunegocio.cloud']);
    $user = User::factory()->create();

    createAccessAudit($user, 'login', '2026-02-01 00:00:00');
    createAccessAudit($user, 'login_failed', '2026-03-01 11:00:00');
    createAccessAudit($master, 'login', '2026-03-01 10:00:00');
    createAccessAudit($user, 'login', '2025-04-01 10:00:00');
    createAccessAudit($user, 'login', '2025-03-31 23:59:59');
    createAccessAudit($user, 'login', '2026-03-02 00:00:00');
    createAccessAudit($user, 'update', '2026-03-01 10:00:00');

    $response = $this->actingAs($master, 'web')->getJson('/api/admin/access-logs/statistics')->assertOk();
    $daily = $response->json('data.daily');
    $monthly = $response->json('data.monthly');

    expect($daily)->toHaveCount(30)
        ->and($daily[0])->toBe(['date' => '2026-03-01', 'count' => 2])
        ->and($daily[28])->toBe(['date' => '2026-02-01', 'count' => 1])
        ->and($daily[29])->toBe(['date' => '2026-01-31', 'count' => 0])
        ->and($monthly)->toHaveCount(12)
        ->and($monthly[0])->toBe(['month' => '2026-03', 'count' => 2])
        ->and($monthly[1])->toBe(['month' => '2026-02', 'count' => 1])
        ->and($monthly[11])->toBe(['month' => '2025-04', 'count' => 1]);
    Carbon::setTestNow();
});

test('access log endpoints are restricted to the master administrator', function () {
    $owner = User::factory()->create();
    $otherRoot = User::factory()->root()->create();

    $urls = ['/api/admin/access-logs', '/api/admin/access-logs/statistics', '/api/admin/access-logs/export'];
    foreach ($urls as $url) {
        $this->getJson($url)->assertUnauthorized();
    }

    foreach ($urls as $url) {
        $this->actingAs($owner, 'web')->getJson($url)->assertForbidden();
        $this->actingAs($otherRoot, 'web')->getJson($url)->assertForbidden();
    }
});

test('listing and xlsx export use the same access events and store formulas as text', function () {
    $master = User::factory()->root()->create(['email' => 'admin@meunegocio.cloud']);
    $user = User::factory()->create(['name' => '=1+1']);
    createAccessAudit($user, 'login', '2026-03-01 10:00:00');
    createAccessAudit($user, 'login_failed', '2026-03-01 11:00:00');
    createAccessAudit($user, 'update', '2026-03-01 12:00:00');
    $this->actingAs($master, 'web');

    $this->getJson('/api/admin/access-logs')
        ->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'failed')
        ->assertJsonPath('data.1.status', 'success');

    $response = $this->get('/api/admin/access-logs/export')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload('logs-de-acesso.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'access-logs-');
    try {
        file_put_contents($file, $response->streamedContent());
        $spreadsheet = IOFactory::createReader('Xlsx')->load($file);
        $sheet = $spreadsheet->getActiveSheet();
        expect($sheet->getTitle())->toBe('Logs de acesso')
            ->and($sheet->getCell('A1')->getValue())->toBe('Usuário')
            ->and($sheet->getCell('E1')->getValue())->toBe('Data/Hora (UTC)')
            ->and($sheet->getHighestRow())->toBe(3)
            ->and($sheet->getCell('A2')->getValue())->toBe('=1+1')
            ->and($sheet->getCell('A2')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('D2')->getValue())->toBe('Falha')
            ->and($sheet->getCell('D3')->getValue())->toBe('Sucesso')
            ->and($sheet->getCell('E2')->getValue())->toBe('01/03/2026 11:00:00');
        $spreadsheet->disconnectWorksheets();
    } finally {
        unlink($file);
    }
});
