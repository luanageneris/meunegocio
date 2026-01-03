# 🏢 Arquitetura Multi-Tenant - Meu Negócio

## Visão Geral

O sistema foi estruturado para suportar **múltiplas empresas (tenants)** desde o início, permitindo escalabilidade futura sem refatorações maiores.

---

## 🗂️ Estrutura de Dados

### Tabela `companies` (Raiz)
- **id**: Identificador único da empresa
- **name**: Nome da empresa (ex: "Xerografia & Cia")
- **slug**: URL amigável (ex: "xerografia-cia")
- **email**, **phone**, **document**: Dados da empresa
- **address**, **city**, **state**, **postal_code**: Localização
- **active**: Status da empresa
- **timestamps** + **soft delete**

### Todas as Tabelas Operacionais
Possuem `company_id` como primeira chave estrangeira:
- `users`
- `collaborators`
- `service_types`
- `entry_types`
- `exit_types`
- `work_shifts`
- `financial_records`
- `commissions`
- `daily_closures`
- `balances`
- `import_logs`

---

## 🔐 Isolamento de Dados

### 1. Middleware de Isolamento
**Arquivo:** `app/Http/Middleware/EnsureCompanyIsolation.php`

- Verifica se o usuário está associado a uma empresa
- Armazena `company_id` na requisição
- Previne acesso não autorizado

**Uso:**
```php
Route::middleware('company.isolation')->group(function () {
    // Rotas protegidas por isolamento
});
```

### 2. Trait BelongsToCompany
**Arquivo:** `app/Traits/BelongsToCompany.php`

Fornece:
- **Scope `forCompany()`**: Filtra dados por empresa
- **Atribuição automática**: Define `company_id` ao criar registros

**Uso em Models:**
```php
use App\Traits\BelongsToCompany;

class Collaborator extends Model
{
    use BelongsToCompany;
}
```

**Exemplo de Query:**
```php
// Obter colaboradores da empresa do usuário atual
Collaborator::forCompany()->get();

// Obter colaboradores de uma empresa específica
Collaborator::forCompany($companyId)->get();
```

### 3. Relacionamentos no Model Company
```php
class Company extends Model
{
    public function users() { return $this->hasMany(User::class); }
    public function collaborators() { return $this->hasMany(Collaborator::class); }
    public function serviceTypes() { return $this->hasMany(ServiceType::class); }
    // ... todos os demais modelos
}
```

---

## 👤 Usuários e Permissões

### Tipos de Usuários

#### 1. **Root (Super Administrador)**
- `role = 'root'`
- Pertence a uma empresa
- Pode:
  - Acessar todos os dados da sua empresa
  - Gerenciar colaboradores, tipos, etc.
  - Importar dados
  - Impersonate outros usuários (futuro)

#### 2. **Owner (Proprietário/Colaborador)**
- `role = 'owner'`
- Pertence a uma empresa
- Acesso apenas aos seus dados

### Estrutura do User
```php
class User extends Model
{
    public function company() { return $this->belongsTo(Company::class); }
    
    public function isRoot(): bool {
        return $this->role === 'root';
    }
}
```

---

## 🔀 Fluxo de Autenticação e Isolamento

```
1. Usuário faz login (Google OAuth ou Email)
   ↓
2. User criado/atualizado com company_id
   ↓
3. Middleware EnsureCompanyIsolation valida
   ↓
4. company_id armazenado em $request->attributes
   ↓
5. Controllers filtram dados via forCompany()
   ↓
6. Trait BelongsToCompany auto-atribui company_id em novos registros
```

---

## 📝 Guia de Implementação nos Controllers

### Exemplo 1: Listar Colaboradores
```php
class CollaboratorController extends Controller
{
    public function index(Request $request)
    {
        // Filtrar pela empresa do usuário atual
        $collaborators = Collaborator::forCompany()->get();
        
        return response()->json($collaborators);
    }
}
```

### Exemplo 2: Criar Colaborador
```php
class CollaboratorController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'cpf' => 'required|unique:collaborators',
        ]);

        // O Trait BelongsToCompany vai atribuir company_id automaticamente
        $collaborator = Collaborator::create($data);

        return response()->json($collaborator, 201);
    }
}
```

### Exemplo 3: Filtrar por Período COM isolamento
```php
class FinancialRecordController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $records = FinancialRecord::forCompany()
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        return response()->json($records);
    }
}
```

---

## 🧪 Testando Multi-Tenant

### Setup de Testes
```php
class FinancialRecordTest extends TestCase
{
    public function test_isolates_data_by_company()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $user1 = User::factory()->create(['company_id' => $company1->id]);
        $user2 = User::factory()->create(['company_id' => $company2->id]);

        $record1 = FinancialRecord::factory()->create(['company_id' => $company1->id]);
        $record2 = FinancialRecord::factory()->create(['company_id' => $company2->id]);

        // User1 só vê records da company1
        $this->actingAs($user1);
        $this->assertEquals(1, FinancialRecord::forCompany()->count());

        // User2 só vê records da company2
        $this->actingAs($user2);
        $this->assertEquals(1, FinancialRecord::forCompany()->count());
    }
}
```

---

## 🚀 Adição de Novas Empresas

### Processo
1. Criar novo `Company`
2. Criar novo usuário `root` com `company_id` apontando para a empresa
3. Seeders automáticos criam tipos de entrada/saída/serviço

### Script Manual
```php
$company = Company::create([
    'name' => 'Nova Empresa LTDA',
    'slug' => 'nova-empresa',
    'email' => 'contato@nova.com.br',
    'active' => true,
]);

$user = User::create([
    'company_id' => $company->id,
    'name' => 'Administrador',
    'email' => 'admin@nova.com.br',
    'password' => bcrypt('senha'),
    'role' => 'root',
]);
```

---

## 📊 Benefícios da Abordagem

✅ **Escalabilidade**: Suporta N empresas  
✅ **Segurança**: Isolamento garantido por middleware + modelo  
✅ **Simplicidade**: Sem overhead desnecessário  
✅ **Automação**: Trait cuida da atribuição automática  
✅ **Flexibilidade**: Fácil adicionar novos filtros  
✅ **Testabilidade**: Testes isolados por empresa  

---

## ⚠️ Checklist para Novos Developers

- [ ] Todos os models operacionais usam `BelongsToCompany`
- [ ] Todas as rotas usam `company.isolation` middleware
- [ ] Controllers usam `.forCompany()` em queries
- [ ] Testes criam dados isolados por empresa
- [ ] Documentação atualizada em README.md

---

**Versão:** 1.0 | **Data:** 03/01/2026 | **Autor:** Arquiteto Full Stack
