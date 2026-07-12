<?php

use App\Models\Company;
use App\Services\ReportPdfService;
use Carbon\Carbon;

it('includes company cnpj and address details when available', function () {
    $service = new ReportPdfService();
    $company = new Company([
        'name' => 'Xerografia & Cia',
        'document' => '12.345.678/0001-99',
        'address' => 'Rua das Flores, 123',
        'city' => 'Fortaleza',
        'state' => 'CE',
        'postal_code' => '60000-000',
    ]);

    $method = new ReflectionMethod(ReportPdfService::class, 'buildCompanyDetailsHtml');
    $method->setAccessible(true);

    $html = $method->invoke($service, $company);

    expect($html)->toContain('CNPJ:');
    expect($html)->toContain('12.345.678/0001-99');
    expect($html)->toContain('Endereço:');
    expect($html)->toContain('Rua das Flores, 123 - Fortaleza - CE - 60000-000');
});

it('supports cnpj alias when document is not set', function () {
    $service = new ReportPdfService();
    $company = new Company([
        'name' => 'Empresa Teste',
        'address' => 'Avenida Central, 456',
        'city' => 'Recife',
        'state' => 'PE',
    ]);

    $company->cnpj = '98.765.432/0001-10';

    $method = new ReflectionMethod(ReportPdfService::class, 'buildCompanyDetailsHtml');
    $method->setAccessible(true);

    $html = $method->invoke($service, $company);

    expect($html)->toContain('CNPJ:');
    expect($html)->toContain('98.765.432/0001-10');
    expect($html)->toContain('Endereço:');
    expect($html)->toContain('Avenida Central, 456 - Recife - PE');
});

it('builds footer html with app link and generated timestamp', function () {
    $service = new ReportPdfService();

    $method = new ReflectionMethod(ReportPdfService::class, 'buildFooterHtml');
    $method->setAccessible(true);

    $html = $method->invoke(
        $service,
        'Meu Negócio',
        'https://meunegocio.app',
        Carbon::create(2026, 7, 12, 14, 35, 0)
    );

    expect($html)->toContain('Meu Negócio');
    expect($html)->toContain('https://meunegocio.app');
    expect($html)->toContain('Gerado em 12/07/2026 14:35:00');
});
