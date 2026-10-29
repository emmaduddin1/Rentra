<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductTourController extends Controller
{
    public function complete(Request $request): Response
    {
        $request->user()->forceFill(['product_tour_completed_at' => now()])->save();

        return response()->noContent();
    }
}
