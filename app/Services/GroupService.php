<?php

namespace App\Services;

use App\Models\Group;

class GroupService
{
    public function list($search = null)
    {
        $query = Group::query();
        if ($search) {
            $query->where('name', 'like', "%$search%");
        }
        return $query->orderBy('name')->paginate(15);
    }

    public function create(array $data): Group
    {
        return Group::create($data);
    }

    public function update(Group $group, array $data): bool
    {
        return $group->update($data);
    }

    public function delete(Group $group): ?bool
    {
        return $group->delete();
    }
}
