<?php

namespace App\Services;

use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\ImportLog;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class FinancialRecordsExcelImportService
{
    public function import(
        UploadedFile $file,
        int $companyId,
        int $importedBy,
        int $defaultEntryTypeId,
        int $defaultExitTypeId,
        int $defaultCollaboratorId
    ): array {
        Log::info('=== INICIANDO IMPORTAÇÃO ===', [
            'file' => $file->getClientOriginalName(),
            'company_id' => $companyId,
            'imported_by' => $importedBy,
        ]);

        $log = ImportLog::create([
            'company_id' => $companyId,
            'file_name' => $file->getClientOriginalName(),
            'imported_by' => $importedBy,
            'status' => 'pending',
            'rows_imported' => 0,
        ]);

        try {
            Log::info('Validando tipos de entrada, saída e colaborador...');
            
            $entryType = EntryType::forCompany()->findOrFail($defaultEntryTypeId);
            Log::info('Entry type encontrado', ['id' => $entryType->id, 'name' => $entryType->name]);
            
            $exitType = ExitType::forCompany()->findOrFail($defaultExitTypeId);
            Log::info('Exit type encontrado', ['id' => $exitType->id, 'name' => $exitType->name]);
            
            $collaborator = Collaborator::forCompany()->findOrFail($defaultCollaboratorId);
            Log::info('Colaborador encontrado', ['id' => $collaborator->id, 'name' => $collaborator->name]);

            Log::info('Carregando arquivo Excel...');
            $spreadsheet = IOFactory::load($file->getRealPath());
            
            $worksheetCount = $spreadsheet->getSheetCount();
            Log::info('Arquivo carregado com sucesso', ['sheets_count' => $worksheetCount]);

            $summary = [
                'entries_created' => 0,
                'exits_created' => 0,
                'commissions_created' => 0,
                'rows_processed' => 0,
                'sheets_processed' => 0,
            ];

            $diagnostics = [
                'invalid_date_rows' => 0,
                'invalid_date_examples' => [],
            ];

            DB::transaction(function () use ($spreadsheet, $companyId, $entryType, $exitType, $collaborator, &$summary, &$diagnostics) {
                foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                    $summary['sheets_processed']++;
                    $sheetName = $sheet->getTitle();
                    Log::info("Processando planilha: $sheetName", ['numero' => $summary['sheets_processed']]);
                    
                    $highestRow = (int) $sheet->getHighestDataRow();
                    Log::info("Maior linha com dados: $highestRow");

                    for ($row = 2; $row <= $highestRow; $row++) {
                        $dateCell = $sheet->getCell('A' . $row);
                        $columnA = trim((string) $dateCell->getFormattedValue());
                        
                        if ($columnA === '') {
                            Log::debug("Linha $row: coluna A vazia, pulando");
                            continue;
                        }

                        if (mb_strtolower($columnA) === 'total') {
                            Log::info("Linha $row: encontrado 'Total', finalizando leitura desta aba");
                            break;
                        }

                        $date = $this->parseDateCell($dateCell);
                        if (!$date) {
                            $diagnostics['invalid_date_rows']++;
                            if (count($diagnostics['invalid_date_examples']) < 5) {
                                $diagnostics['invalid_date_examples'][] = $columnA;
                            }
                            Log::debug("Linha $row: data inválida ou mal formatada ($columnA)");
                            continue;
                        }

                        $summary['rows_processed']++;
                        Log::info("Linha $row: Data válida ({$date->format('d/m/Y')})");

                        $entryCell = $sheet->getCell('B' . $row);
                        $commissionCell = $sheet->getCell('C' . $row);
                        $exitCell = $sheet->getCell('D' . $row);
                        $columnB = $entryCell->getFormattedValue();
                        $columnC = $commissionCell->getFormattedValue();
                        $columnD = $exitCell->getFormattedValue();
                        $columnF = $sheet->getCell('F' . $row)->getFormattedValue();
                        
                        Log::debug("Valores brutos", [
                            'B' => $columnB,
                            'C' => $columnC,
                            'D' => $columnD,
                            'F' => $columnF,
                        ]);

                        // ENTRADA
                        $entryAmount = $this->parseCurrencyCell($entryCell);
                        if ($entryAmount > 0) {
                            Log::info("Criando ENTRADA", [
                                'data' => $date->toDateString(),
                                'amount' => $entryAmount,
                                'type_id' => $entryType->id,
                            ]);
                            
                            FinancialRecord::create([
                                'company_id' => $companyId,
                                'type' => 'entry',
                                'entry_type_id' => $entryType->id,
                                'amount' => $entryAmount,
                                'quantity' => 1,
                                'discount' => 0,
                                'date' => $date->toDateString(),
                                'description' => 'Importado de planilha',
                            ]);
                            $summary['entries_created']++;
                            Log::info("ENTRADA criada com sucesso", ['total' => $summary['entries_created']]);
                        } else {
                            Log::debug("Coluna B (entrada): valor 0 ou vazio, não criando");
                        }

                        // COMISSÃO
                        $commissionAmount = $this->parseCurrencyCell($commissionCell);
                        if ($commissionAmount > 0) {
                            Log::info("Criando COMISSÃO", [
                                'data' => $date->toDateString(),
                                'amount' => $commissionAmount,
                                'collaborator_id' => $collaborator->id,
                            ]);
                            
                            Commission::create([
                                'company_id' => $companyId,
                                'collaborator_id' => $collaborator->id,
                                'financial_record_id' => null,
                                'date' => $date->toDateString(),
                                'amount' => $commissionAmount,
                                'paid_at' => null,
                                'notes' => 'Comissão importada de planilha',
                            ]);
                            $summary['commissions_created']++;
                            Log::info("COMISSÃO criada com sucesso", ['total' => $summary['commissions_created']]);
                        } else {
                            Log::debug("Coluna C (comissão): valor 0 ou vazio, não criando");
                        }

                        // SAÍDA
                        $exitAmount = $this->parseCurrencyCell($exitCell);
                        if ($exitAmount > 0) {
                            $reason = trim((string) $columnF);
                            Log::info("Criando SAÍDA", [
                                'data' => $date->toDateString(),
                                'amount' => $exitAmount,
                                'reason' => $reason ?: 'Sem descrição',
                                'type_id' => $exitType->id,
                            ]);
                            
                            FinancialRecord::create([
                                'company_id' => $companyId,
                                'type' => 'exit',
                                'exit_type_id' => $exitType->id,
                                'amount' => $exitAmount,
                                'quantity' => 1,
                                'discount' => 0,
                                'date' => $date->toDateString(),
                                'description' => $reason !== '' ? $reason : 'Importado de planilha',
                            ]);
                            $summary['exits_created']++;
                            Log::info("SAÍDA criada com sucesso", ['total' => $summary['exits_created']]);
                        } else {
                            Log::debug("Coluna D (saída): valor 0 ou vazio, não criando");
                        }
                    }
                }
            });

            $totalImported = $summary['entries_created'] + $summary['exits_created'] + $summary['commissions_created'];

            if ($summary['rows_processed'] === 0) {
                throw new RuntimeException($this->buildInvalidDateMessage($diagnostics));
            }

            if ($totalImported === 0) {
                throw new RuntimeException('Nenhum registro foi criado. Verifique se as colunas de entrada, comissão e saída possuem valores válidos maiores que zero.');
            }

            Log::info('=== IMPORTAÇÃO CONCLUÍDA COM SUCESSO ===', [
                'sheets_processed' => $summary['sheets_processed'],
                'rows_processed' => $summary['rows_processed'],
                'entries_created' => $summary['entries_created'],
                'exits_created' => $summary['exits_created'],
                'commissions_created' => $summary['commissions_created'],
                'total_imported' => $totalImported,
            ]);
            
            $log->update([
                'status' => 'success',
                'rows_imported' => $totalImported,
                'error_message' => null,
            ]);

            return [
                'success' => true,
                'status' => 201,
                'payload' => [
                    'message' => 'Importação concluída com sucesso',
                    'summary' => $summary,
                    'log_id' => $log->id,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('=== ERRO NA IMPORTAÇÃO ===', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            $errorMessage = $this->getReadableErrorMessage($e);
            $log->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'status' => 422,
                'payload' => [
                    'message' => 'Falha ao importar arquivo',
                    'error' => $errorMessage,
                    'details' => $e->getMessage(),
                    'summary' => $summary ?? null,
                ],
            ];
        }
    }

    private function parseCurrencyCell(Cell $cell): float
    {
        $formattedValue = trim((string) $cell->getFormattedValue());
        $rawValue = $cell->getValue();
        $formatCode = (string) $cell->getStyle()->getNumberFormat()->getFormatCode();

        Log::debug('parseCurrencyCell: analisando célula', [
            'formatted_value' => $formattedValue,
            'raw_value' => is_scalar($rawValue) ? $rawValue : gettype($rawValue),
            'format_code' => $formatCode,
        ]);

        if (is_numeric($rawValue)) {
            $parsed = (float) $rawValue;
            $result = $parsed > 0 ? $parsed : 0;

            Log::debug('parseCurrencyCell: sucesso por valor numérico bruto', [
                'raw_value' => $rawValue,
                'output' => $result,
                'format_code' => $formatCode,
            ]);

            return $result;
        }

        return $this->parseCurrencyString($formattedValue);
    }

    private function parseCurrencyString(mixed $value): float
    {
        if (is_numeric($value)) {
            $parsed = (float) $value;
            Log::debug('parseCurrencyString: valor numérico', ['input' => $value, 'output' => $parsed]);
            return $parsed;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            Log::debug('parseCurrencyString: valor vazio');
            return 0;
        }

        $normalized = preg_replace('/[^\d,.-]/', '', $raw) ?? '';
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        if (!is_numeric($normalized)) {
            Log::debug('parseCurrencyString: valor não numérico após normalização', ['input' => $value, 'normalized' => $normalized]);
            return 0;
        }

        $amount = (float) $normalized;
        $result = $amount > 0 ? $amount : 0;
        Log::debug('parseCurrencyString: sucesso', ['input' => $value, 'normalized' => $normalized, 'output' => $result]);
        return $result;
    }

    private function parseDateCell(Cell $cell): ?Carbon
    {
        $formattedValue = trim((string) $cell->getFormattedValue());
        $rawValue = $cell->getValue();
        $formatCode = (string) $cell->getStyle()->getNumberFormat()->getFormatCode();

        if ($formattedValue === '' && ($rawValue === null || $rawValue === '')) {
            Log::debug("parseDate: valor vazio");
            return null;
        }

        Log::debug('parseDate: analisando célula', [
            'formatted_value' => $formattedValue,
            'raw_value' => is_scalar($rawValue) ? $rawValue : gettype($rawValue),
            'format_code' => $formatCode,
        ]);

        if (is_numeric($rawValue) && ExcelDate::isDateTime($cell)) {
            try {
                $date = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $rawValue))->startOfDay();
                Log::debug('parseDate: sucesso por data serial do Excel', [
                    'raw_value' => $rawValue,
                    'parsed' => $date->toDateString(),
                    'format_code' => $formatCode,
                ]);

                return $date;
            } catch (\Throwable $e) {
                Log::debug('parseDate: falha ao converter data serial do Excel', [
                    'raw_value' => $rawValue,
                    'format_code' => $formatCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $candidateValues = array_values(array_unique(array_filter([
            $formattedValue,
            is_scalar($rawValue) ? trim((string) $rawValue) : null,
        ])));

        foreach ($candidateValues as $candidate) {
            $date = $this->parseDateStringUsingDetectedFormats($candidate, $formatCode);
            if ($date !== null) {
                return $date;
            }
        }

        Log::debug('parseDate: nenhum formato suportado reconhecido', [
            'formatted_value' => $formattedValue,
            'raw_value' => is_scalar($rawValue) ? $rawValue : gettype($rawValue),
            'format_code' => $formatCode,
        ]);

        return null;
    }

    private function parseDateStringUsingDetectedFormats(string $value, string $formatCode = ''): ?Carbon
    {
        $normalizedValue = trim($value);
        if ($normalizedValue === '') {
            return null;
        }

        $formats = $this->resolveCandidateDateFormats($formatCode, $normalizedValue);

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $normalizedValue);
                if ($date->format($format) !== $normalizedValue) {
                    continue;
                }

                Log::debug('parseDate: sucesso por formato textual', [
                    'input' => $normalizedValue,
                    'parsed' => $date->toDateString(),
                    'format' => $format,
                    'format_code' => $formatCode,
                ]);

                return $date->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function resolveCandidateDateFormats(string $formatCode, string $value): array
    {
        $normalizedCode = strtolower($formatCode);

        $monthFirstFormats = ['n/j/Y', 'm/d/Y', 'n/d/Y', 'm/j/Y'];
        $dayFirstFormats = ['j/n/Y', 'd/m/Y', 'j/m/Y', 'd/n/Y'];
        $isoFormats = ['Y-m-d', 'Y/m/d'];
        $otherFormats = ['d-m-Y', 'm-d-Y', 'd.m.Y', 'm.d.Y'];

        $detectedFormats = [];

        if ($this->looksLikeMonthFirstFormatCode($normalizedCode)) {
            $detectedFormats = array_merge($detectedFormats, $monthFirstFormats, $dayFirstFormats);
        } elseif ($this->looksLikeDayFirstFormatCode($normalizedCode)) {
            $detectedFormats = array_merge($detectedFormats, $dayFirstFormats, $monthFirstFormats);
        } elseif (preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $value) === 1) {
            $detectedFormats = array_merge($detectedFormats, $isoFormats, $dayFirstFormats, $monthFirstFormats);
        } else {
            $detectedFormats = array_merge($detectedFormats, $dayFirstFormats, $monthFirstFormats);
        }

        return array_values(array_unique(array_merge($detectedFormats, $isoFormats, $otherFormats)));
    }

    private function looksLikeMonthFirstFormatCode(string $formatCode): bool
    {
        if ($formatCode === '') {
            return false;
        }

        return str_contains($formatCode, 'm/d')
            || str_contains($formatCode, 'mm/dd')
            || str_contains($formatCode, 'm-dd')
            || str_contains($formatCode, 'mm-dd');
    }

    private function looksLikeDayFirstFormatCode(string $formatCode): bool
    {
        if ($formatCode === '') {
            return false;
        }

        return str_contains($formatCode, 'd/m')
            || str_contains($formatCode, 'dd/mm')
            || str_contains($formatCode, 'd-mm')
            || str_contains($formatCode, 'dd-mm');
    }

    private function getReadableErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        if ($e instanceof RuntimeException) {
            return $message;
        }

        // Erros de validação
        if (str_contains($message, 'does not exist')) {
            return 'Um dos valores padrão selecionados não foi encontrado. Verifique se ele ainda existe.';
        }

        // Erros de arquivo
        if (str_contains($message, 'Invalid stream position')) {
            return 'O arquivo está corrompido ou não é um arquivo Excel válido.';
        }

        if (str_contains($message, 'file')) {
            return 'Erro ao processar o arquivo. Verifique se ele é um arquivo Excel válido (.xlsx ou .xls).';
        }

        // Erros de dados
        if (str_contains($message, 'UNIQUE') || str_contains($message, 'Duplicate')) {
            return 'Alguns registros já existem no sistema. Importação parcial realizada.';
        }

        // Erro padrão
        return 'Erro ao processar a importação. Tente novamente.';
    }

    private function buildInvalidDateMessage(array $diagnostics): string
    {
        $examples = implode(', ', $diagnostics['invalid_date_examples']);

        if ($examples !== '') {
            return 'Nenhuma linha válida foi encontrada no arquivo. As datas lidas estão em formato inválido para a importação atual. Exemplos: ' . $examples . '. Use datas no formato dd/mm/aaaa.';
        }

        return 'Nenhuma linha válida foi encontrada no arquivo. Use datas no formato dd/mm/aaaa.';
    }
}