<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Expense;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $invoicesThisMonth = Document::query()
            ->where('document_type', 'factura')
            ->whereBetween('issue_date', [$monthStart, $monthEnd]);

        $salesTotal = (clone $invoicesThisMonth)->where('operation_type', 'venta')->sum('total');
        $purchasesTotal = (clone $invoicesThisMonth)->where('operation_type', 'compra')->sum('total');

        // Expenses never show up on a document's own total, but they're real costs
        // that eat into this month's profit.
        $expensesTotal = Expense::whereHas(
            'document',
            fn ($query) => $query->whereBetween('issue_date', [$monthStart, $monthEnd])
        )->sum('amount');

        $receivable = Document::query()
            ->where('document_type', 'factura')
            ->where('operation_type', 'venta')
            ->whereIn('status', ['pendiente', 'parcial'])
            ->get()
            ->sum(fn (Document $document) => $document->balance());

        $recent = Document::query()
            ->with('contact:id,name')
            ->where('document_type', 'factura')
            ->whereBetween('issue_date', [$monthStart, $monthEnd])
            ->latest('issue_date')
            ->latest('id')
            ->limit(8)
            ->get();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'sales_total' => (float) $salesTotal,
                'purchases_total' => (float) $purchasesTotal,
                'expenses_total' => (float) $expensesTotal,
                'profit' => (float) $salesTotal - (float) $purchasesTotal - (float) $expensesTotal,
                'receivable' => (float) $receivable,
            ],
            'recentDocuments' => $recent,
        ]);
    }
}
