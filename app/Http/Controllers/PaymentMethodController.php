<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    /**
     * Lightweight JSON listing used for the payment method picker (with optional search).
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('q')->toString();

        $paymentMethods = PaymentMethod::query()
            ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($paymentMethods);
    }

    /**
     * Quick-create a payment method from the picker when it doesn't exist yet.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('payment_methods', 'name')],
        ]);

        $paymentMethod = PaymentMethod::create($data);

        return response()->json($paymentMethod, 201);
    }
}
