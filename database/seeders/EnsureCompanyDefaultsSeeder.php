<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\CompanyDefaultsService;
use Illuminate\Database\Seeder;

class EnsureCompanyDefaultsSeeder extends Seeder
{
    public function __construct(private CompanyDefaultsService $companyDefaultsService)
    {
    }

    public function run(): void
    {
        Company::query()
            ->select('id')
            ->chunkById(100, function ($companies): void {
                foreach ($companies as $company) {
                    $this->companyDefaultsService->ensureForCompany((int) $company->id);
                }
            });
    }
}
