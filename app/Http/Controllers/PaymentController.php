<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Document;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Document $document): RedirectResponse
    {
        if ($document->document_type !== 'factura') {
            abort(422, 'Solo las facturas admiten abonos.');
        }

        $data = $request->validated();

        if ((float) $data['amount'] > $document->balance()) {
            throw ValidationException::withMessages([
                'amount' => __('El abono no puede superar el saldo pendiente (:balance).', ['balance' => number_format($document->balance(), 2)]),
            ]);
        }

        $document->payments()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Abono registrado.')]);

        return back();
    }

    public function destroy(Document $document, Payment $payment): RedirectResponse
    {
        $payment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Abono eliminado.')]);

        return back();
    }
}
