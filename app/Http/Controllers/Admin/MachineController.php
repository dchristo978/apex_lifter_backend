<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MachineController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/machines/index', [
            'machines' => Machine::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/machines/form', ['machine' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Machine::create($this->validated($request));

        return to_route('admin.machines.index');
    }

    public function edit(Machine $machine): Response
    {
        return Inertia::render('admin/machines/form', ['machine' => $machine]);
    }

    public function update(Request $request, Machine $machine): RedirectResponse
    {
        $machine->update($this->validated($request));

        return to_route('admin.machines.index');
    }

    public function destroy(Machine $machine): RedirectResponse
    {
        $machine->delete();

        return to_route('admin.machines.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
