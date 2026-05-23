<?php

namespace App\Http\Controllers\Settings\Billing;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only invoice list with PDF download. The PDF generator is a
 * minimal text-only PDF here so the download flow is wired E2E; the
 * real production deploy swaps in your invoicing template.
 */
class InvoiceController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'organization.manage-billing');

        $invoices = Invoice::query()
            ->where('organization_id', $org->id)
            ->orderByDesc('issued_on')
            ->limit(100)
            ->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'issued_on' => $i->issued_on->toIso8601String(),
                'due_on' => $i->due_on?->toIso8601String(),
                'amount_cents' => $i->amount_cents,
                'amount_formatted' => number_format($i->amount_cents / 100, 2),
                'currency' => $i->currency,
                'status' => $i->status,
                'paid_at' => $i->paid_at?->toIso8601String(),
                'line_items' => $i->line_items,
            ]);

        return Inertia::render('settings/billing/invoices', [
            'invoices' => $invoices,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Invoices', 'href' => '/settings/billing/invoices'],
            ],
        ]);
    }

    public function download(Request $request, Invoice $invoice): StreamedResponse|HttpResponse
    {
        $org = $this->org($request, 'organization.manage-billing');
        abort_if($invoice->organization_id !== $org->id, 403);

        $pdf = $this->renderTextPdf($invoice, $org->name);

        return response()->stream(
            function () use ($pdf) {
                echo $pdf;
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="invoice-'.$invoice->number.'.pdf"',
            ],
        );
    }

    /**
     * Hand-built minimal PDF: one page, plain text body. Good enough to
     * verify the route and download lifecycle without a PDF library.
     */
    private function renderTextPdf(Invoice $invoice, string $orgName): string
    {
        $lines = [];
        $lines[] = 'INVOICE '.$invoice->number;
        $lines[] = 'Issued: '.$invoice->issued_on->format('Y-m-d');
        $lines[] = $invoice->due_on ? 'Due: '.$invoice->due_on->format('Y-m-d') : 'Due: on receipt';
        $lines[] = 'Org: '.$orgName;
        $lines[] = '';
        foreach ((array) $invoice->line_items as $row) {
            $lines[] = sprintf('  %-40s %s %.2f', (string) ($row['label'] ?? ''), $invoice->currency, ((int) ($row['amount_cents'] ?? 0)) / 100);
        }
        $lines[] = '';
        $lines[] = sprintf('TOTAL: %s %.2f', $invoice->currency, $invoice->amount_cents / 100);
        $body = implode("\n", $lines);

        $content = "BT\n/F1 12 Tf\n50 750 Td\n14 TL\n";
        foreach (explode("\n", $body) as $line) {
            $content .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
        }
        $content .= 'ET';

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
            "4 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= $obj;
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }
}
