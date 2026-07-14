<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;

class MachineController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'machines' => Machine::orderBy('category')->orderBy('name')->get(),
        ]);
    }
}
