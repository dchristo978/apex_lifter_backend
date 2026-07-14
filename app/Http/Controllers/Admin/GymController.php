<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GymController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/gyms/index', [
            'gyms' => Gym::withCount('checkins')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/gyms/form', ['gym' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gym::create($this->validated($request));

        return to_route('admin.gyms.index');
    }

    public function edit(Gym $gym): Response
    {
        return Inertia::render('admin/gyms/form', ['gym' => $gym]);
    }

    public function update(Request $request, Gym $gym): RedirectResponse
    {
        $gym->update($this->validated($request));

        return to_route('admin.gyms.index');
    }

    public function destroy(Gym $gym): RedirectResponse
    {
        $gym->delete();

        return to_route('admin.gyms.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'checkin_radius_m' => ['required', 'integer', 'min:10', 'max:5000'],
        ]);
    }
}
