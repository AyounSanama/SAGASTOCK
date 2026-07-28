<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'countries' => Country::where('is_active', true)->orderBy('name')->get(['id', 'iso2', 'name']),
        ]);
    }
}
