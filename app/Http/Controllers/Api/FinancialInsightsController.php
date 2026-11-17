<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Services\FinancialInsightsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FinancialInsightsController extends Controller
{
    public function opportunities(Request $request, FinancialInsightsService $service)
    {
        $validated = Validator::make($request->all(), [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'category' => ['nullable', 'string', 'max:64'],
        ], [
            'start_date.required' => 'Informe a data inicial do período.',
            'start_date.date_format' => 'A data inicial deve estar no formato AAAA-MM-DD.',
            'end_date.required' => 'Informe a data final do período.',
            'end_date.date_format' => 'A data final deve estar no formato AAAA-MM-DD.',
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ])->validate();

        $companyId = (int) $request->user()->company_id;
        abort_if($companyId <= 0, 403, 'A sua conta não está associada a uma empresa.');

        $startDate = Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $validated['end_date'])->startOfDay();

        if ($endDate->greaterThan(today())) {
            throw ValidationException::withMessages([
                'end_date' => 'A data final não pode ser posterior a hoje.',
            ]);
        }

        if ($endDate->greaterThan($startDate->copy()->addYears(2))) {
            throw ValidationException::withMessages([
                'start_date' => 'O intervalo entre as datas não pode ser superior a 2 anos.',
            ]);
        }

        $category = $this->resolveCategory(
            (string) ($validated['category'] ?? 'all'),
            $companyId,
        );
        $openingDays = $request->user()->company?->opening_days ?? range(0, 6);

        return response()->json([
            'data' => $service->opportunities($companyId, $startDate, $endDate, $category, $openingDays),
        ]);
    }

    public function curiosity(Request $request, string $kind, FinancialInsightsService $service)
    {
        $validated = Validator::make($request->all(), [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ], [
            'month.required' => 'Informe o mês para consultar as curiosidades.',
            'month.integer' => 'O mês selecionado não é válido.',
            'month.between' => 'O mês deve estar entre 1 e 12.',
            'year.required' => 'Informe o ano para consultar as curiosidades.',
            'year.integer' => 'O ano selecionado não é válido.',
            'year.between' => 'O ano deve estar entre 2000 e 2100.',
        ])->validate();

        $companyId = (int) $request->user()->company_id;
        abort_if($companyId <= 0, 403, 'A sua conta não está associada a uma empresa.');

        return response()->json([
            'data' => $service->curiosity(
                $companyId,
                $kind,
                (int) $validated['month'],
                (int) $validated['year'],
            ),
        ]);
    }

    private function resolveCategory(string $value, int $companyId): array
    {
        if ($value === 'all') {
            return ['type' => 'all', 'id' => null];
        }

        if (! preg_match('/^(entry|exit):([1-9]\d*)$/', $value, $matches)) {
            throw ValidationException::withMessages([
                'category' => 'A categoria selecionada não é válida.',
            ]);
        }

        $type = $matches[1];
        $id = (int) $matches[2];
        $model = $type === 'entry' ? EntryType::class : ExitType::class;

        if (! $model::forCompany($companyId)->whereKey($id)->where('active', true)->exists()) {
            throw ValidationException::withMessages([
                'category' => 'A categoria selecionada não pertence à sua empresa ou está inativa.',
            ]);
        }

        return ['type' => $type, 'id' => $id];
    }
}
