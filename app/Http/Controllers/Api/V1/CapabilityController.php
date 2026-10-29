<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Features;
use Illuminate\Http\JsonResponse;

class CapabilityController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'property_sales' => Features::propertySales(),
            ],
        ]);
    }
}
