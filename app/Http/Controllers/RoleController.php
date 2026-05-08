<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $roles = Role::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                       ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->withCount(['users', 'modulePermissions'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.roles.index', compact('roles', 'search'));
    }

    public function create()
    {
        $role = new Role();
        $modules = $this->groupedModules();
        $selected = [];

        return view('admin.roles.form', [
            'role' => $role,
            'modules' => $modules,
            'selected' => $selected,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRole($request);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_admin' => (bool) ($data['is_admin'] ?? false),
            ]);

            $role->syncModules($data['modules'] ?? []);

            return $role;
        });

        return redirect()->route('roles.edit', $role)->with('success', 'Cargo criado com sucesso.');
    }

    public function edit(Role $role)
    {
        $modules = $this->groupedModules();
        $selected = $role->moduleKeys();

        return view('admin.roles.form', [
            'role' => $role,
            'modules' => $modules,
            'selected' => $selected,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validateRole($request, $role->id);

        DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_admin' => (bool) ($data['is_admin'] ?? false),
            ]);

            $role->syncModules($data['modules'] ?? []);
        });

        return redirect()->route('roles.edit', $role)->with('success', 'Cargo atualizado com sucesso.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->with('error', 'Não é possível excluir um cargo com usuários vinculados.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Cargo excluído.');
    }

    /**
     * @param array<int, string> $modules
     */
    protected function validateRole(Request $request, ?int $ignoreId = null): array
    {
        $validModuleKeys = array_keys(config('modules.modules', []));

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('roles', 'name')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_admin' => ['nullable', 'boolean'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in($validModuleKeys)],
        ]);
    }

    /**
     * Retorna os módulos agrupados pela chave `group` para renderização na UI.
     *
     * @return array<string, array<string, array{label:string,description:?string}>>
     */
    protected function groupedModules(): array
    {
        $grouped = [];

        foreach (config('modules.modules', []) as $key => $meta) {
            $group = $meta['group'] ?? 'Geral';
            $grouped[$group][$key] = [
                'label' => $meta['label'] ?? $key,
                'description' => $meta['description'] ?? null,
            ];
        }

        ksort($grouped);

        return $grouped;
    }
}
