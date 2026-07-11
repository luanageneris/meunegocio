<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Services\CompanyDefaultsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DefaultRecordsProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_entry_type_cannot_be_renamed_or_removed(): void
    {
        $company = Company::create([
            'name' => 'Default Entry Company',
            'slug' => 'default-entry-company',
            'email' => 'entry-default@example.com',
            'phone' => '123456789',
            'document' => '11.111.111/1111-11',
            'address' => 'Test Address',
        ]);

        $default = EntryType::create([
            'company_id' => $company->id,
            'name' => CompanyDefaultsService::DEFAULT_LABEL,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Este registro padrão não pode ser alterado');

        $default->update(['name' => 'Outro nome']);
    }

    public function test_default_exit_type_cannot_be_renamed_or_removed(): void
    {
        $company = Company::create([
            'name' => 'Default Exit Company',
            'slug' => 'default-exit-company',
            'email' => 'exit-default@example.com',
            'phone' => '123456789',
            'document' => '22.222.222/2222-22',
            'address' => 'Test Address',
        ]);

        $default = ExitType::create([
            'company_id' => $company->id,
            'name' => CompanyDefaultsService::DEFAULT_EXIT_TYPE_COMMISSION_LABEL,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Este registro padrão não pode ser removido');

        $default->delete();
    }

    public function test_default_collaborator_cannot_be_renamed_or_removed(): void
    {
        $company = Company::create([
            'name' => 'Default Collaborator Company',
            'slug' => 'default-collaborator-company',
            'email' => 'collaborator-default@example.com',
            'phone' => '123456789',
            'document' => '33.333.333/3333-33',
            'address' => 'Test Address',
        ]);

        $default = Collaborator::create([
            'company_id' => $company->id,
            'name' => CompanyDefaultsService::DEFAULT_LABEL,
            'cpf' => '000.000.000-01',
            'active' => true,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Este registro padrão não pode ser alterado');

        $default->update(['name' => 'Outro nome']);
    }
}