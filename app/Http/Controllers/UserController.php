<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'id', 'name', 'email', 'role', 'last_login_at', 'created_at',
    ];

    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sort = in_array($request->sort, self::SORTABLE_COLUMNS, true) ? $request->sort : 'id';
        $direction = $request->direction ?? 'desc';
        if (! in_array(strtolower($direction), ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $users = $query->orderBy($sort, $direction)->paginate(20)->appends($request->query());

        return Inertia::render('superadmin/users/index', [
            'users' => $users,
            'roles' => [
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'super_admin', 'label' => 'Super Admin'],
                ['value' => 'caregiver', 'label' => 'Caregiver'],
                ['value' => 'client', 'label' => 'Client'],
            ],
            'filters' => [
                'search' => $request->search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        User::create([
            'name' => trim($request->first_name.' '.$request->last_name),
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update([
            'name' => trim($request->first_name.' '.$request->last_name),
            'email' => $request->email,
            'role' => $request->role,
        ]);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Password reset successfully.');
    }
}
