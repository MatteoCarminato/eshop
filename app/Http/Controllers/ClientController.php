<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\ClientService;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ClientService $clientService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = $request->get('search');
        $type   = $request->get('type');

        $clients = $this->clientService->filter($search, $type, 50);

        return view('admin.clients.index', compact('clients', 'search', 'type'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.clients.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        try {
            $this->clientService->create($request);

            return redirect()
                ->route('clients.index')
                ->with('success', 'Cliente cadastrado com sucesso!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Erro ao cadastrar cliente: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client): View
    {
        return view('admin.clients.show', compact('client'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client): View
    {
        return view('admin.clients.edit', compact('client'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        try {
            $this->clientService->update($request, $client);

            return redirect()
                ->route('clients.index')
                ->with('success', 'Cliente atualizado com sucesso!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Erro ao atualizar cliente: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client): RedirectResponse
    {
        try {
            $this->clientService->delete($client);

            return redirect()
                ->route('clients.index')
                ->with('success', 'Cliente excluído com sucesso!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Erro ao excluir cliente: ' . $e->getMessage());
        }
    }
    public function addToGroups(Request $request): RedirectResponse
    {
        $groupId = $request->input('group_id');
        $clientIds = explode(',', $request->input('client_ids', ''));
        $group = \App\Models\Group::findOrFail($groupId);
        if (!empty($clientIds)) {
            $group->clients()->syncWithoutDetaching($clientIds);
        }
        return redirect()->route('clients.index')->with('success', 'Clientes adicionados ao grupo!');
    }
}
