<?php

namespace App\Http\Controllers;

use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(private GroupService $service) {}

    public function index(Request $request)
    {
        $groups = $this->service->list($request->input('search'));
        return view('admin.groups.index', compact('groups'));
    }

    public function create()
    {
        return view('admin.groups.create');
    }

    public function store(StoreGroupRequest $request)
    {
        $group = $this->service->create($request->validated());
        return redirect()->route('groups.index')->with('success', 'Grupo criado com sucesso!');
    }

    public function show(Group $group)
    {
        $group->load('clients');
        return view('admin.groups.show', compact('group'));
    }

    public function edit(Group $group)
    {
        $group->load('clients');
        return view('admin.groups.edit', compact('group'));
    }

    public function update(UpdateGroupRequest $request, Group $group)
    {
        $this->service->update($group, $request->validated());
        return redirect()->route('groups.index')->with('success', 'Grupo atualizado com sucesso!');
    }

    public function destroy(Group $group)
    {
        $this->service->delete($group);
        return redirect()->route('groups.index')->with('success', 'Grupo excluído com sucesso!');
    }

    public function addClients(Request $request, Group $group)
    {
        $clientIds = $request->input('clients', []);
        if (!empty($clientIds)) {
            $group->clients()->syncWithoutDetaching($clientIds);
        }
        return redirect()->route('groups.show', $group)->with('success', 'Clientes adicionados ao grupo!');
    }
}
