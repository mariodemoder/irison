<?php

namespace Modules\Subscriptions\Infrastructure\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        $pricing = config('pricing');

        return response()->json(['data' => $pricing]);
    }
}
