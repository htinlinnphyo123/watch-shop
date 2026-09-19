<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchServiceExpense extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'expense_date' => 'date', 'voided_at' => 'datetime'];
}
