<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PreOrder;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Entity\SheetView;

class SalesWorkbookExport
{
    public const SALES_HEADERS = ['No', 'Fb Acc', 'Customer Name', 'Customer Gender', 'Address', 'Phone', 'Seller', 'Brand', 'Model No.', 'Watch Gender', 'Order Date', 'Order Type', 'Buying Type', 'Paid by', 'Payment Type', 'Pre-Order Payment', 'Price', 'Discount', 'Opening', 'Total Payment', 'Delivery Type', 'Delivery ID', 'Delivery Status', 'Delivery Fees', 'Received Money', 'Marketing Channel', 'Remark', 'Order Number', 'Order Status', 'Money Transfer Amount'];
    public const PRE_ORDER_HEADERS = ['No', 'Fb Acc', 'Customer Name', 'Address', 'Phone', 'Brand', 'Model No.', 'Order Date', 'Order type', 'Buying Type', 'Paid by', 'Payment Type', 'Price', 'Discount', 'Deposit', 'Opening', 'Total Payment', 'Delivery Type', 'Delivery ID', 'Delivery Status', 'Delivery Fees', 'Received Money'];

    public function saleRow(Order $order): array
    {
        $customer = $order->customer;
        $subtotal = $order->items->sum(fn ($line) => (float) $line->price * $line->quantity);
        $payments = $order->payments ?: ($order->payment_method ? [['method' => $order->payment_method]] : []);
        $methods = ['cash' => 'Cash', 'kbz_pay' => 'KBZ Pay', 'cb_pay' => 'CB Pay', 'aya_pay' => 'AYA Pay', 'card' => 'Card', 'transfer' => 'Bank Transfer', 'cod' => 'COD', 'other' => 'Other'];
        $paymentMethods = collect($payments)->pluck('method')->unique();
        $paidBy = $paymentMethods->map(fn ($method) => $methods[$method] ?? ucfirst($method))->implode(', ');
        $retained = (new OrderSummaryService())->retainedPayments($order);
        $received = $retained === null ? null : array_sum($retained) / 100;
        return array_combine(self::SALES_HEADERS, [
            $order->id, $customer?->source_details, $customer?->name ?? 'Walk-in Customer', $this->label($customer?->gender), $customer?->address, $customer?->phone,
            $order->salespeople->pluck('name')->implode(', '), $order->items->map(fn ($line) => $line->product?->brand?->name)->filter()->unique()->implode(', '),
            $order->items->map(fn ($line) => ($line->product?->model_number ?: $line->product?->name ?: 'Deleted product').($line->quantity > 1 ? ' (x'.$line->quantity.')' : ''))->implode(', '),
            $order->items->map(fn ($line) => $line->product?->gender)->filter()->unique()->implode(', '),
            $order->order_date ?? $order->created_at, $this->label($order->order_type), $this->label($order->buying_type), $paidBy,
            $paidBy ?: null, null, // No separate pre-order payment breakdown is stored.
            round($subtotal, 2), $this->money($order->discount_percentage) === null ? null : (float) $order->discount_percentage / 100,
            $received === null ? null : max(0, round((float) $order->total_amount + (float) $order->delivery_fees - (float) $order->money_transfer_amount - $received, 2)),
            (float) $order->total_amount, $order->delivery_type, $order->delivery_code, $order->delivery_status,
            $this->money($order->delivery_fees), $received, $order->marketing_channel, $order->remark,
            $order->order_number, $this->label($order->status), $this->money($order->money_transfer_amount),
        ]);
    }

    /** One row per physical watch; order-level amounts appear only on the first row. */
    public function saleRows(Order $order): iterable
    {
        $base = $this->saleRow($order);
        $first = true;
        foreach ($order->items as $line) {
            for ($unit = 0; $unit < $line->quantity; $unit++) {
                $row = $base;
                $row['Brand'] = $line->product?->brand?->name;
                $row['Model No.'] = $line->product?->model_number ?: $line->product?->name ?: 'Deleted product';
                $row['Watch Gender'] = $line->product?->gender;
                $row['Price'] = (float) $line->price;
                $discount = $line->discount_percentage ?? $order->discount_percentage;
                $row['Discount'] = $discount === null ? null : (float) $discount / 100;
                if (! $first) {
                    foreach (['Opening', 'Total Payment', 'Delivery Fees', 'Received Money', 'Money Transfer Amount'] as $header) {
                        $row[$header] = null;
                    }
                }
                yield $row;
                $first = false;
            }
        }
        // Keep legacy orders without items visible in the export too.
        if ($first) yield $base;
    }

    public function preOrderRow(PreOrder $record): array
    {
        $total = $record->price === null ? null : round((float) $record->price - (float) $record->discount_amount, 2);
        return array_combine(self::PRE_ORDER_HEADERS, [
            $record->id, $record->customer?->source_details, $record->customer?->name, $record->customer?->address, $record->customer?->phone,
            $record->brand?->name, $record->model_number ?: $record->product?->model_number,
            $record->order_date ?? $record->created_at, $record->type === 'reservation' ? 'Reservation' : 'Preorder',
            $this->label($record->buying_type), $record->paid_by, $this->label($record->payment_type), $this->money($record->price), $this->money($record->discount_amount),
            $this->money($record->deposit_amount), $total === null || $record->deposit_amount === null ? null : max(0, round($total - (float) $record->deposit_amount, 2)),
            $total, $record->delivery_type, $record->delivery_code, $record->delivery_status, $this->money($record->delivery_fees), $this->money($record->money_transfer_amount),
        ]);
    }

    private function money($value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function label(?string $value): ?string
    {
        return $value === null ? null : (['in_person' => 'Inperson', 'preorder' => 'Preorder', 'instock' => 'Instock', 'cod' => 'COD', 'foc' => 'FOC', 'mbanking' => 'Mbanking'][$value] ?? ucfirst($value));
    }

    public function download(iterable $rows, bool $preOrder = false)
    {
        $sheet = $preOrder ? 'Strap Order' : 'Sales';
        return response()->streamDownload(function () use ($rows, $preOrder, $sheet) {
            $this->write('php://output', $rows, $preOrder, $sheet);
        }, ($preOrder ? 'strap-orders-' : 'sales-').now()->format('Y-m-d_His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function write(string $path, iterable $rows, bool $preOrder = false, ?string $sheet = null): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        try {
            $headers = $preOrder ? self::PRE_ORDER_HEADERS : self::SALES_HEADERS;
            $current = $writer->getCurrentSheet();
            $current->setName($sheet ?? ($preOrder ? 'Strap Order' : 'Sales'));
            $current->setSheetView((new SheetView())->setFreezeRow(2));
            $current->setColumnWidthForRange(22, 1, count($headers));
            foreach ($headers as $index => $header) {
                if (in_array($header, ['Address', 'Model No.', 'Remark', 'Fb Acc', 'Marketing Channel'], true)) $current->setColumnWidth(35, $index + 1);
            }
            $writer->addRow(Row::fromValues($headers, (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('374151')->setShouldWrapText()));
            foreach ($rows as $data) {
                $cells = [];
                foreach ($headers as $header) {
                    $value = $data[$header] ?? null;
                    $style = (new Style())->setShouldWrapText();
                    if ($value instanceof \DateTimeInterface) $style->setFormat('yyyy-mm-dd');
                    elseif (is_float($value)) $style->setFormat($header === 'Discount' && ! $preOrder ? '0.00%' : '#,##0.00');
                    // Explicit StringCell prevents formula-like customer input becoming executable formulas.
                    $cells[] = is_string($value) ? new \OpenSpout\Common\Entity\Cell\StringCell($value, $style) : Cell::fromValue($value, $style);
                }
                $writer->addRow(new Row($cells));
            }
        } finally {
            $writer->close();
        }
    }
}
