<?php

use App\Models\Company;

test('new companies default to service types mode disabled', function () {
    $company = Company::create([
        'name' => 'Empresa Teste',
        'slug' => 'empresa-teste',
    ]);

    expect($company->use_service_types)->toBeFalse();
});