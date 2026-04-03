<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;

    protected function setUp(): void
    {
        parent::setUp();

        usleep(10000);
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);

        $this->company = Company::create([
            'name' => 'Import Company ' . $ts,
            'slug' => 'import-company-' . $ts,
            'email' => 'import+' . $ts . '@test.com',
            'phone' => '123456789',
            'document' => $ts . '.345.678/0001-97',
            'address' => 'Test Address',
        ]);

        $this->rootUser = User::create([
            'name' => 'Root User Import',
            'email' => 'root+import+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-import-' . $ts,
            'role' => 'root',
        ]);
    }

    public function test_can_import_excel_with_multiple_sheets_and_defaults(): void
    {
        $entryType = EntryType::create([
            'company_id' => $this->company->id,
            'name' => 'Não definido',
        ]);

        $exitType = ExitType::create([
            'company_id' => $this->company->id,
            'name' => 'Não definido',
        ]);

        $collaborator = Collaborator::create([
            'company_id' => $this->company->id,
            'name' => 'Não definido',
            'cpf' => '999.888.777-66',
            'active' => true,
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Janeiro');
        $sheet1->fromArray([
            ['Data', 'Total de Entrada', 'Total de Comissões', 'Pagamentos (Saida)', 'Total Final', 'motivo da saida'],
            ['01/01/2026', 'R$ 100,00', 'R$ 15,00', 'R$ 40,00', 'R$ 45,00', 'Energia'],
            ['02/01/2026', '', 'R$ 20,00', '', '', ''],
            ['03/01/2026', '0', '0', 'texto inválido', '', ''],
            ['Total', '', '', '', '', ''],
        ]);

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Fevereiro');
        $sheet2->fromArray([
            ['Data', 'Total de Entrada', 'Total de Comissões', 'Pagamentos (Saida)', 'Total Final', 'motivo da saida'],
            ['03/02/2026', '', '', 'R$ 10,00', '', 'Internet'],
            ['Total', '', '', '', '', ''],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'import_');
        $xlsxPath = $tempPath . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        $file = new UploadedFile(
            $xlsxPath,
            'registros.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->rootUser, 'sanctum')->post('/api/import', [
            'file' => $file,
            'default_entry_type_id' => $entryType->id,
            'default_exit_type_id' => $exitType->id,
            'default_collaborator_id' => $collaborator->id,
        ], [
            'Accept' => 'application/json',
        ]);

        @unlink($xlsxPath);
        @unlink($tempPath);

        $response->assertStatus(201)
            ->assertJsonPath('data.summary.entries_created', 1)
            ->assertJsonPath('data.summary.exits_created', 2)
            ->assertJsonPath('data.summary.commissions_created', 2)
            ->assertJsonPath('data.summary.sheets_processed', 2);

        $this->assertDatabaseCount('financial_records', 3);
        $this->assertDatabaseCount('commissions', 2);
        $this->assertDatabaseHas('import_logs', [
            'company_id' => $this->company->id,
            'status' => 'success',
            'rows_imported' => 5,
        ]);
    }
}
