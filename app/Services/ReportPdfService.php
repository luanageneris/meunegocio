<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FinancialRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportPdfService
{
    private const MONTHLY_COL_DATE = 60;
    private const MONTHLY_COL_TYPE = 45;
    private const MONTHLY_COL_CATEGORY = 95;
    private const MONTHLY_COL_DESCRIPTION = 150;
    private const MONTHLY_COL_ENTRY = 60;
    private const MONTHLY_COL_EXIT = 60;
    private const MONTHLY_COL_BALANCE = 60;

    private const YEARLY_COL_MONTH = 120;
    private const YEARLY_COL_ENTRY = 85;
    private const YEARLY_COL_EXIT = 85;
    private const YEARLY_COL_BALANCE = 85;
    private const YEARLY_COL_STATUS = 100;

    private const TABLE_FONT_SIZE = '9px';

    public function buildMonthlyStatementPdf(Company $company, Carbon $periodStart, string $pin): string
    {
        $periodStart = $periodStart->copy()->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $records = FinancialRecord::query()
            ->where('company_id', $company->id)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereIn('type', ['entry', 'exit'])
            ->with(['entryType', 'exitType'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $generatedAt = now();
        $pdf = $this->createPdfDocument('Relatório Mensal - Extrato', $pin, $generatedAt);
        $pdf->AddPage();

        $html = '';
        $html .= '<h2>Relatório Mensal - Extrato</h2>';
        $html .= '<p><strong>Empresa:</strong> ' . e($company->name) . '<br/>';
        $html .= $this->buildCompanyDetailsHtml($company);
        $html .= '<strong>Período:</strong> ' . $periodStart->format('m/Y') . '</p>';

        $html .= '<table border="1" cellpadding="3" cellspacing="0" style="font-size:' . self::TABLE_FONT_SIZE . ';">';
        $html .= '<thead>';
        $html .= '<tr style="background-color:#f0f0f0; font-weight:bold;">';
        $html .= '<th width="' . self::MONTHLY_COL_DATE . '">Data</th>';
        $html .= '<th width="' . self::MONTHLY_COL_TYPE . '">Tipo</th>';
        $html .= '<th width="' . self::MONTHLY_COL_CATEGORY . '">Categoria</th>';
        $html .= '<th width="' . self::MONTHLY_COL_DESCRIPTION . '">Descrição</th>';
        $html .= '<th width="' . self::MONTHLY_COL_ENTRY . '" align="right">Entrada</th>';
        $html .= '<th width="' . self::MONTHLY_COL_EXIT . '" align="right">Saída</th>';
        $html .= '<th width="' . self::MONTHLY_COL_BALANCE . '" align="right">Saldo</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $balance = 0.0;
        foreach ($records as $index => $record) {
            $amount = (float) $record->final_amount;
            $entryAmount = $record->type === 'entry' ? $amount : 0.0;
            $exitAmount = $record->type === 'exit' ? $amount : 0.0;
            $balance += $entryAmount - $exitAmount;
            $rowBackground = $index % 2 === 0 ? '#ffffff' : '#f5f5f5';

            $category = $record->type === 'entry'
                ? ($record->entryType?->name ?? '-')
                : ($record->exitType?->name ?? '-');

            $html .= '<tr style="background-color:' . $rowBackground . ';">';
            $html .= '<td width="' . self::MONTHLY_COL_DATE . '">' . optional($record->date)->format('d/m/Y') . '</td>';
            $html .= '<td width="' . self::MONTHLY_COL_TYPE . '">' . ($record->type === 'entry' ? 'Entrada' : 'Saída') . '</td>';
            $html .= '<td width="' . self::MONTHLY_COL_CATEGORY . '">' . e($category) . '</td>';
            $html .= '<td width="' . self::MONTHLY_COL_DESCRIPTION . '">' . e((string) ($record->description ?? '-')) . '</td>';
            $html .= '<td width="' . self::MONTHLY_COL_ENTRY . '" align="right">' . $this->currency($entryAmount) . '</td>';
            $html .= '<td width="' . self::MONTHLY_COL_EXIT . '" align="right">' . $this->currency($exitAmount) . '</td>';
            $html .= '<td width="' . self::MONTHLY_COL_BALANCE . '" align="right">' . $this->currency($balance) . '</td>';
            $html .= '</tr>';
        }

        if ($records->isEmpty()) {
            $html .= '<tr style="background-color:#ffffff;"><td colspan="7" align="center">Sem movimentações no período.</td></tr>';
        }

        $totalEntries = (float) $records->where('type', 'entry')->sum(fn (FinancialRecord $record) => $record->final_amount);
        $totalExits = (float) $records->where('type', 'exit')->sum(fn (FinancialRecord $record) => $record->final_amount);
        $finalBalance = $totalEntries - $totalExits;

        $html .= '<tr style="font-weight:bold; background-color:#f9f9f9;">';
        $monthlyLabelWidth = self::MONTHLY_COL_DATE + self::MONTHLY_COL_TYPE + self::MONTHLY_COL_CATEGORY + self::MONTHLY_COL_DESCRIPTION;
        $html .= '<td width="' . $monthlyLabelWidth . '" align="right">Totais</td>';
        $html .= '<td width="' . self::MONTHLY_COL_ENTRY . '" align="right">' . $this->currency($totalEntries) . '</td>';
        $html .= '<td width="' . self::MONTHLY_COL_EXIT . '" align="right">' . $this->currency($totalExits) . '</td>';
        $html .= '<td width="' . self::MONTHLY_COL_BALANCE . '" align="right">' . $this->currency($finalBalance) . '</td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $path = tempnam(sys_get_temp_dir(), 'monthly-report-');
        if ($path === false) {
            throw new \RuntimeException('Falha ao criar arquivo temporário para o relatório mensal.');
        }

        $finalPath = $path . '.pdf';
        $pdf->Output($finalPath, 'F');

        return $finalPath;
    }

    public function buildYearlySummaryPdf(Company $company, int $year, string $pin): string
    {
        $generatedAt = now();
        $pdf = $this->createPdfDocument('Relatório Anual - Resumo', $pin, $generatedAt);
        $pdf->AddPage();

        $html = '';
        $html .= '<h2>Relatório Anual - Resumo</h2>';
        $html .= '<p><strong>Empresa:</strong> ' . e($company->name) . '<br/>';
        $html .= $this->buildCompanyDetailsHtml($company);
        $html .= '<strong>Ano:</strong> ' . $year . '</p>';

        $html .= '<table border="1" cellpadding="3" cellspacing="0" style="font-size:' . self::TABLE_FONT_SIZE . ';">';
        $html .= '<thead>';
        $html .= '<tr style="background-color:#f0f0f0; font-weight:bold;">';
        $html .= '<th width="' . self::YEARLY_COL_MONTH . '">Mês</th>';
        $html .= '<th width="' . self::YEARLY_COL_ENTRY . '" align="right">Entradas</th>';
        $html .= '<th width="' . self::YEARLY_COL_EXIT . '" align="right">Saídas</th>';
        $html .= '<th width="' . self::YEARLY_COL_BALANCE . '" align="right">Saldo</th>';
        $html .= '<th width="' . self::YEARLY_COL_STATUS . '">Status</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $totalEntries = 0.0;
        $totalExits = 0.0;

        for ($month = 1; $month <= 12; $month++) {
            $entries = FinancialRecord::query()
                ->where('company_id', $company->id)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->where('type', 'entry')
                ->sum(DB::raw(FinancialRecord::FINAL_AMOUNT_SQL));

            $exits = FinancialRecord::query()
                ->where('company_id', $company->id)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->where('type', 'exit')
                ->sum(DB::raw(FinancialRecord::FINAL_AMOUNT_SQL));

            $entries = (float) $entries;
            $exits = (float) $exits;
            $balance = $entries - $exits;

            $totalEntries += $entries;
            $totalExits += $exits;
            $rowBackground = $month % 2 === 1 ? '#ffffff' : '#f5f5f5';

            $monthLabel = Carbon::create($year, $month, 1)->locale('pt_BR')->translatedFormat('F');

            $html .= '<tr style="background-color:' . $rowBackground . ';">';
            $html .= '<td width="' . self::YEARLY_COL_MONTH . '">' . e(ucfirst($monthLabel)) . '</td>';
            $html .= '<td width="' . self::YEARLY_COL_ENTRY . '" align="right">' . $this->currency($entries) . '</td>';
            $html .= '<td width="' . self::YEARLY_COL_EXIT . '" align="right">' . $this->currency($exits) . '</td>';
            $html .= '<td width="' . self::YEARLY_COL_BALANCE . '" align="right">' . $this->currency($balance) . '</td>';
            $html .= '<td width="' . self::YEARLY_COL_STATUS . '">' . ($balance < 0 ? 'Déficit' : 'Sem déficit') . '</td>';
            $html .= '</tr>';
        }

        $totalBalance = $totalEntries - $totalExits;

        $html .= '<tr style="font-weight:bold; background-color:#f9f9f9;">';
        $html .= '<td width="' . self::YEARLY_COL_MONTH . '" align="right">Total anual</td>';
        $html .= '<td width="' . self::YEARLY_COL_ENTRY . '" align="right">' . $this->currency($totalEntries) . '</td>';
        $html .= '<td width="' . self::YEARLY_COL_EXIT . '" align="right">' . $this->currency($totalExits) . '</td>';
        $html .= '<td width="' . self::YEARLY_COL_BALANCE . '" align="right">' . $this->currency($totalBalance) . '</td>';
        $html .= '<td width="' . self::YEARLY_COL_STATUS . '">' . ($totalBalance < 0 ? 'Déficit' : 'Sem déficit') . '</td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $path = tempnam(sys_get_temp_dir(), 'yearly-report-');
        if ($path === false) {
            throw new \RuntimeException('Falha ao criar arquivo temporário para o relatório anual.');
        }

        $finalPath = $path . '.pdf';
        $pdf->Output($finalPath, 'F');

        return $finalPath;
    }

    private function currency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function createPdfDocument(string $title, string $pin, Carbon $generatedAt): \TCPDF
    {
        $appName = (string) config('app.name');
        $appUrl = trim((string) config('app.url'));
        $footerHtml = $this->buildFooterHtml($appName, $appUrl, $generatedAt);

        $pdf = new class($footerHtml) extends \TCPDF
        {
            public function __construct(private readonly string $footerHtml)
            {
                parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
            }

            public function Footer(): void
            {
                $this->SetY(-12);
                $this->SetFont('helvetica', '', 7);
                $this->writeHTMLCell(0, 0, '', '', $this->footerHtml, 0, 0, false, true, 'C', true);
            }
        };

        $pdf->SetCreator($appName);
        $pdf->SetAuthor($appName);
        $pdf->SetTitle($title);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetFooterMargin(6);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetProtection(['print'], $pin, null, 0, null);

        return $pdf;
    }

    private function buildFooterHtml(string $appName, string $appUrl, Carbon $generatedAt): string
    {
        $generatedLabel = $generatedAt->copy()->format('d/m/Y H:i:s');
        $appLink = $appUrl !== ''
            ? '<a href="' . e($appUrl) . '" color="#666666">' . e($appName) . '</a> - ' . e($appUrl)
            : e($appName);

        return '<div style="font-size:7px; color:#666666; text-align:center;">'
            . $appLink
            . ' | Gerado em ' . e($generatedLabel)
            . '</div>';
    }

    private function buildCompanyDetailsHtml(Company $company): string
    {
        $details = '';
        $companyDocument = $this->resolveCompanyDocument($company);
        $details .= '<strong>CNPJ:</strong> ' . e($companyDocument !== '' ? $companyDocument : 'Não informado') . '<br/>';

        $address = $this->resolveCompanyAddress($company);
        $details .= '<strong>Endereço:</strong> ' . e($address !== '' ? $address : 'Não informado') . '<br/>';

        return $details;
    }

    private function resolveCompanyDocument(Company $company): string
    {
        $documentCandidates = [
            $company->document,
            $company->cnpj,
            $company->cpf_cnpj,
            $company->tax_id,
        ];

        foreach ($documentCandidates as $candidate) {
            if (!empty($candidate)) {
                return trim((string) $candidate);
            }
        }

        return '';
    }

    private function resolveCompanyAddress(Company $company): string
    {
        $addressCandidates = [
            $company->address,
            $company->endereco,
            $company->full_address,
        ];

        foreach ($addressCandidates as $candidate) {
            if (!empty($candidate)) {
                $baseAddress = trim((string) $candidate);
                break;
            }
        }

        $addressParts = array_filter([
            $baseAddress ?? null,
            $company->city,
            $company->state,
            $company->postal_code,
        ], fn ($value) => !empty($value));

        if ($addressParts === []) {
            return '';
        }

        return trim(implode(' - ', $addressParts));
    }
}
