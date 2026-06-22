<?php

namespace App\Services;

use App\Models\Client;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientService
{
    /**
     * List all clients.
     *
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function list(?int $perPage = null): Collection|LengthAwarePaginator
    {
        if ($perPage) {
            return Client::orderBy('created_at', 'desc')->paginate($perPage);
        }

        return Client::orderBy('created_at', 'desc')->get();
    }

    public function filter(?string $search, ?string $type, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = Client::orderBy('created_at', 'desc');

        if ($type === 'cliente' || $type === 'fornecedor') {
            $query->where('type', $type);
        }

        if ($search) {
            $term = strtolower($search);
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"]);
            });
        }

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Find a client by ID.
     *
     * @param int $id
     * @return Client
     */
    public function findById(int $id): Client
    {
        return Client::findOrFail($id);
    }

    /**
     * Create a new client.
     *
     * @param StoreClientRequest $request
     * @return Client
     */
    public function create(StoreClientRequest $request): Client
    {
        // Com os novos mutators, a normalização é automática
        $data = $request->validated();

        return Client::create($data);
    }

    /**
     * Update an existing client.
     *
     * @param UpdateClientRequest $request
     * @param Client $client
     * @return Client
     */
    public function update(UpdateClientRequest $request, Client $client): Client
    {
        // Com os novos mutators, a normalização é automática
        $data = $request->validated();

        $client->update($data);

        return $client->fresh();
    }

    /**
     * Delete a client.
     *
     * @param Client $client
     * @return bool
     * @throws \Exception
     */
    public function delete(Client $client): bool
    {
        // Aqui você pode adicionar verificações antes de deletar
        // Por exemplo: verificar se o cliente tem pedidos associados
        
        // if ($client->orders()->exists()) {
        //     throw new \Exception('Não é possível deletar um cliente com pedidos.');
        // }

        return $client->delete();
    }

    /**
     * Search clients by name or email.
     *
     * @param string $search
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function search(string $search, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $term = strtolower($search);
        $query = Client::whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
            ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])
            ->orderBy('created_at', 'desc');

        if ($perPage) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Check if email exists.
     *
     * @param string $email
     * @param int|null $exceptId
     * @return bool
     */
    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $query = Client::where('email', $email);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}
