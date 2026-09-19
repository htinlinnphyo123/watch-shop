<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchService extends Model
{
    public const STATUSES = [
        'received' => 'Watch Received', 'inspection' => 'Under Inspection',
        'awaiting_approval' => 'Awaiting Customer Approval',
        'sent_to_company' => 'Sent to Company',
        'sent_to_shop' => 'Sent to Other Watch Shop',
        'external_repair' => 'Being Repaired by Other Shop',
        'returned_from_shop' => 'Received Back from Other Shop',
        'repairing' => 'Repair in Progress', 'ready' => 'Ready for Collection',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const DECISIONS = ['not_applicable' => 'Not a warranty claim', 'pending' => 'Needs review', 'approved' => 'Warranty approved', 'rejected' => 'Warranty declined'];

    public const FAULTS = ['unknown' => 'Not determined yet', 'human_damage' => 'Accidental / handling damage', 'mechanical' => 'Mechanical / movement fault', 'manufacturing' => 'Manufacturing defect', 'maintenance' => 'Routine maintenance', 'other' => 'Other'];

    public const CLOSED = ['completed', 'cancelled'];

    public const REPAIR_FIELDS = ['fault_type', 'diagnosis', 'repair_provider', 'external_shop_name', 'external_shop_phone', 'external_reference', 'provider_notes', 'billing_status', 'customer_charge', 'charge_notes'];

    protected $guarded = [];

    protected $casts = ['warranty_snapshot' => 'array', 'issue_date' => 'date', 'customer_charge' => 'decimal:2'];

    public function expenses()
    {
        return $this->hasMany(WatchServiceExpense::class);
    }

    public function events()
    {
        return $this->hasMany(WatchServiceEvent::class);
    }

    public function unit()
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id')->withTrashed();
    }
}
