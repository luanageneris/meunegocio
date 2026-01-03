<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::firstOrCreate(
            ['slug' => 'xerografia-cia'],
            [
                'name' => 'Xerografia & Cia',
                'slug' => 'xerografia-cia',
                'email' => 'contato@xerografia.com.br',
                'phone' => '(11) 3000-0000',
                'document' => '00.000.000/0000-00',
                'address' => 'Rua das Cópias, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01000-000',
                'active' => true,
            ]
        );
    }
}
