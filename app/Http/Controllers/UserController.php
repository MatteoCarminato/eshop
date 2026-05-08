<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));

        $users = User::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        $roles = Role::query()->select(['id', 'name'])->orderBy('name')->get();

        return view('admin.users.form', [
            'user' => new User(),
            'roles' => $roles,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
        ]);

        return redirect()->route('users.index')->with('success', 'Funcionário cadastrado com sucesso.');
    }

    public function edit(User $user): View
    {
        $roles = Role::query()->select(['id', 'name'])->orderBy('name')->get();

        return view('admin.users.form', [
            'user' => $user,
            'roles' => $roles,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validateUser($request, $user->id);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role_id = $data['role_id'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'Funcionário atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'Você não pode excluir seu próprio usuário.');
        }

        User::query()->whereKey($user->id)->delete();

        return redirect()->route('users.index')->with('success', 'Funcionário excluído com sucesso.');
    }

    protected function validateUser(Request $request, ?int $ignoreId = null): array
    {
        $passwordRules = $ignoreId
            ? ['nullable', 'string', Password::defaults()]
            : ['required', 'string', Password::defaults()];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($ignoreId),
            ],
            'password' => $passwordRules,
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);
    }
}
