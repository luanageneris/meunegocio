<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private EntryType $entryType;
    private ExitType $exitType;
    private Collaborator $collaborator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->root()->create(['company_id' => $this->company->id]);
        $this->entryType = EntryType::factory()->create(['company_id' => $this->company->id]);
        $this->exitType = ExitType::factory()->create(['company_id' => $this->company->id]);
        $this->collaborator = Collaborator::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->user);
    }

    private function createExcelFile(array $data): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('B1', 'Total de entrada');
        $sheet->setCellValue('C1', 'Total comissões');
        $sheet->setCellValue('D1', 'Pagamentos');
        $sheet->setCellValue('F1', 'Nota');

        // Data
        foreach ($data as $row => $values) {
            $rowNum = $row + 2;
            $sheet->setCellValue("A$rowNum", $values['date']);
            $sheet->setCellValue("B$rowNum", $values['entry'] ?? '');
            $sheet->setCellValue("C$rowNum", $values['commission'] ?? '');
            $sheet->setCellValue("D$rowNum", $values['exit'] ?? '');
            $sheet->setCellValue("F$rowNum", $values['nota'] ?? '');
        }

        // Salvar em arquivo temporário
        $filePath = Storage::path('test_import.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return new UploadedFile(
            $filePath,
            'test_import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_import_success_returns_summary()
    {
        $data = [
            ['date' => '01/01/2025', 'entry' => 'R$ 100,00', 'commission' => 'R$ 50,00', 'exit' => 'R$ 30,00'],
            ['date' => '02/01/2025', 'entry' => 'R$ 200,00', 'commission' => 'R$ 100,00', 'exit' => 'R$ 50,00'],
        ];

        $file = $this->createExcelFile($data);

        $response = $this->postJson('/api/import', [
            'file' => $file,
            'default_entry_type_id' => $this->entryType->id,
            'default_exit_type_id' => $this->exitType->id,
            'default_collaborator_id' => $this->collaborator->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.message', 'Importação concluída com sucesso');
        $response->assertJsonPath('data.summary.entries_created', 2);
        $response->assertJsonPath('data.summary.exits_created', 2);
        $response->assertJsonPath('data.summary.commissions_created', 2);
        $response->assertJsonPath('data.summary.sheets_processed', 1);
    }

    public function test_import_accepts_month_first_string_dates()
    {
        $data = [
            ['date' => '1/2/2025', 'entry' => 'R$ 100,00', 'commission' => 'R$ 50,00', 'exit' => 'R$ 30,00'],
            ['date' => '1/3/2025', 'entry' => 'R$ 200,00', 'commission' => 'R$ 60,00', 'exit' => 'R$ 40,00'],
        ];

        $file = $this->createExcelFile($data);

        $response = $this->postJson('/api/import', [
            'file' => $file,
            'default_entry_type_id' => $this->entryType->id,
            'default_exit_type_id' => $this->exitType->id,
            'default_collaborator_id' => $this->collaborator->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.summary.rows_processed', 2);
        $response->assertJsonPath('data.summary.entries_created', 2);
        $response->assertJsonPath('data.summary.exits_created', 2);
        $response->assertJsonPath('data.summary.commissions_created', 2);
    }

    public function test_import_accepts_excel_serial_dates()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('B1', 'Total de entrada');
        $sheet->setCellValue('C1', 'Total comissões');
        $sheet->setCellValue('D1', 'Pagamentos');
        $sheet->setCellValue('F1', 'Nota');

        $sheet->setCellValue('A2', 45659);
        $sheet->getStyle('A2')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_XLSX14);
        $sheet->setCellValue('B2', 'R$ 120,00');
        $sheet->setCellValue('C2', 'R$ 20,00');
        $sheet->setCellValue('D2', 'R$ 10,00');

        $filePath = Storage::path('test_import_serial.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $file = new UploadedFile(
            $filePath,
            'test_import_serial.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/import', [
            'file' => $file,
            'default_entry_type_id' => $this->entryType->id,
            'default_exit_type_id' => $this->exitType->id,
            'default_collaborator_id' => $this->collaborator->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.summary.rows_processed', 1);
        $response->assertJsonPath('data.summary.entries_created', 1);
        $response->assertJsonPath('data.summary.exits_created', 1);
        $response->assertJsonPath('data.summary.commissions_created', 1);
    }

    public function test_import_accepts_numeric_currency_cells_with_excel_format()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('B1', 'Total de entrada');
        $sheet->setCellValue('C1', 'Total comissões');
        $sheet->setCellValue('D1', 'Pagamentos');
        $sheet->setCellValue('F1', 'Nota');

        $sheet->setCellValue('A2', '22/04/2025');
        $sheet->setCellValue('B2', 198);
        $sheet->setCellValue('C2', 80.5);
        $sheet->setCellValue('D2', 117.5);
        $sheet->getStyle('B2:D2')->getNumberFormat()->setFormatCode('R$ #,##0.00');

        $filePath = Storage::path('test_import_currency.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $file = new UploadedFile(
            $filePath,
            'test_import_currency.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/import', [
            'file' => $file,
            'default_entry_type_id' => $this->entryType->id,
            'default_exit_type_id' => $this->exitType->id,
            'default_collaborator_id' => $this->collaborator->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.summary.rows_processed', 1);
        $response->assertJsonPath('data.summary.entries_created', 1);
        $response->assertJsonPath('data.summary.exits_created', 1);
        $response->assertJsonPath('data.summary.commissions_created', 1);
    }

    public function test_import_creates_import_log()
    {
        $data = [
            ['date' => '01/01/2025', 'entry' => 'R$ 100,00', 'commission' => 'R$ 50,00', 'exit' => 'R$ 30,00'],
        ];

        $file = $this->createExcelFile($data);

        $this->postJson('/api/import', [
            'file' => $file,
            'default_entry_type_id' => $this->entryType->id,
            'default_exit_type_id' => $this->exitType->id,
            'default_collaborator_id' => $this->collaborator->id,
        ]);

        $log = ImportLog::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('success', $log->status);
        $this->assertEquals(3, $log->rows_imported); // 1 entry + 1 exit + 1 commission
    }

    public function test_import_fails_when_file_has_no_valid_rows()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers sem dados válidos
        $sheet->setCellValue('A1', 'Data');

        $filePath = Storage::path('test_import.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $file = new UploadedFile(
            $filePath,
            'test_import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/import', [
            'file' => $file,
            'default_entry_type_id' => $this->entryType->id,
            'default_exit_type_id' => $this->exitType->id,
            'default_collaborator_id' => $this->collaborator->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('data.message', 'Falha ao importar arquivo');
        $response->assertJsonPath('data.error', 'Nenhuma linha válida foi encontrada no arquivo. Use datas no formato dd/mm/aaaa.');
    }

    public function test_import_fails_when_dates_are_in_unsupported_format()
    {
        $data = [
            ['date' => '2025.31.01', 'entry' => 'R$ 100,00', 'commission' => 'R$ 50,00', 'exit' => 'R$ 30,00'],
            ['date' => '2025.30.01', 'entry' => 'R$ 200,00', 'commission' => 'R$ 60,00', 'exit' => 'R$ 40,00'],
        ];

        $file = $this->createExcelFile($data);

        $response = $this->postJson('/api/import', [
            'file' => $file,
            'default_entry_type_id' => $this->entryType->id,
            'default_exit_type_id' => $this->exitType->id,
            'default_collaborator_id' => $this->collaborator->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('data.message', 'Falha ao importar arquivo');
        $response->assertJsonPath(
            'data.error',
            'Nenhuma linha válida foi encontrada no arquivo. As datas lidas estão em formato inválido para a importação atual. Exemplos: 2025.31.01, 2025.30.01. Use datas no formato dd/mm/aaaa.'
        );

        $log = ImportLog::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('failed', $log->status);
    }

    public function test_import_validates_entry_type()
    {
        $data = [
            ['date' => '01/01/2025', 'entry' => 'R$ 100,00'],
        ];

        $file = $this->createExcelFile($data);

        $response = $this->postJson('/api/import', [
            'file' => $file,
            'default_entry_type_id' => 99999, // ID inválido
            'default_exit_type_id' => $this->exitType->id,
            'default_collaborator_id' => $this->collaborator->id,
        ]);

        // Validação do request retorna 422 com mensagem padrão do Laravel
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The selected default entry type id is invalid.');
    }
}
