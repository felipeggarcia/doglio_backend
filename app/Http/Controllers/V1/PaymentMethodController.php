<?php

namespace App\Http\Controllers\V1;

use App\Models\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::where('is_active', true)->get();

        return PaymentMethodResource::collection($methods);
    }
}
