<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderOutput;
use App\Services\PurchaseOrderOutputDocumentBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PurchaseOrderOutputPrintController extends Controller
{
    public function __invoke(
        PurchaseOrderOutput $purchaseOrderOutput,
        Request $request,
        PurchaseOrderOutputDocumentBuilder $builder
    ) {
        $user = $request->user();

        $canView = $user
            && ($user->can('view_purchase_order_output') || $user->can('view_any_purchase_order_output'));

        abort_unless($canView, 403);

        $filename = sprintf('%s.pdf', $purchaseOrderOutput->document_number);
        $viewData = $builder->build($purchaseOrderOutput);
        $viewData['filename'] = $filename;

        if ($request->query('format') === 'pdf') {
            $pdf = Pdf::loadView('documents.purchase-order-output', $viewData)
                ->setPaper('a4', 'portrait');

            return $request->boolean('download')
                ? $pdf->download($filename)
                : $pdf->stream($filename);
        }

        return view('documents.purchase-order-output', $viewData);
    }
}
