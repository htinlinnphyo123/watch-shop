<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\WalletPaymentType;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

class WalletWorkbookExport
{
    public const HEADERS = ['ID', 'User', 'Date', 'In / Out', 'Category', 'Payment Type', 'Amount', 'Currency', 'Balance After', 'Description', 'Recorded By', 'Voucher'];

    public function download(iterable $transactions)
    {
        return response()->streamDownload(function () use ($transactions) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            try {
                $sheet = $writer->getCurrentSheet();
                $sheet->setName('Wallet Records');
                $sheet->setSheetView((new SheetView())->setFreezeRow(2));
                $sheet->setColumnWidthForRange(22, 1, count(self::HEADERS));
                $sheet->setColumnWidth(35, 10, 12);
                $writer->addRow(Row::fromValues(self::HEADERS, (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('374151')->setShouldWrapText()));
                $categories = ExpenseCategory::options();
                $paymentTypes = WalletPaymentType::options();
                foreach ($transactions as $transaction) {
                    $values = [
                        $transaction->id, $transaction->wallet?->user?->name, $transaction->created_at,
                        $transaction->type === 'credit' ? 'In' : 'Out',
                        $categories[$transaction->category] ?? $transaction->category,
                        $paymentTypes[$transaction->payment_type] ?? $transaction->payment_type,
                        (float) $transaction->amount, $transaction->wallet?->currency,
                        (float) $transaction->balance_after, $transaction->description,
                        $transaction->createdBy?->name ?? 'System', $transaction->attachment_name,
                    ];
                    $cells = [];
                    foreach ($values as $value) {
                        $style = (new Style())->setShouldWrapText();
                        if ($value instanceof \DateTimeInterface) $style->setFormat('yyyy-mm-dd hh:mm');
                        elseif (is_float($value)) $style->setFormat('#,##0.00');
                        // Keep descriptions and user-entered names literal, never executable formulas.
                        $cells[] = is_string($value) ? new StringCell($value, $style) : Cell::fromValue($value, $style);
                    }
                    $writer->addRow(new Row($cells));
                }
            } finally {
                $writer->close();
            }
        }, 'wallet-records-'.now()->format('Y-m-d_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
