<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\ServiceType;
use Illuminate\Http\Request;

class MasterDataStatusController extends Controller
{
    public function update(Request $request, string $kind, int $id)
    {
        abort_unless($request->user()->canManageFinancialRecords(), 403, 'Apenas o proprietário pode alterar o estado.');

        $models = [
            'entry-types' => EntryType::class,
            'exit-types' => ExitType::class,
            'service-types' => ServiceType::class,
            'collaborators' => Collaborator::class,
        ];
        abort_unless(isset($models[$kind]), 404);
        $data = $request->validate(['active' => 'required|boolean']);
        $item = $models[$kind]::where('company_id', $request->user()->company_id)->findOrFail($id);

        $name = mb_strtolower(trim($item->name));
        if (!$data['active'] && (in_array($name, ['não definido', 'nao definido'], true)
            || ($item instanceof ExitType && in_array($name, ['comissão', 'comissao'], true)))) {
            return response()->json(['message' => 'Este item padrão não pode ser colocado para dormir.'], 422);
        }

        $item->forceFill(['active' => $data['active']])->save();

        return response()->json(['data' => $item]);
    }
}
