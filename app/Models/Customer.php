<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    public const REGIONS = [
        'Ayeyarwady Region',
        'Bago Region',
        'Magway Region',
        'Mandalay Region',
        'Sagaing Region',
        'Tanintharyi Region',
        'Yangon Region',
        'Chin State',
        'Kachin State',
        'Kayah State',
        'Kayin State',
        'Mon State',
        'Rakhine State',
        'Shan State',
        'NayPyiTaw',
    ];

    public const SOURCES = [
        'tiktok' => 'TikTok',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'referral' => 'Referred by a person',
        'walk_in' => 'Walk-in',
        'other' => 'Other',
    ];

    use HasApiTokens, HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = ['is_guest' => 'boolean'];

    protected $hidden = [
        'password',
    ];

    public function group()
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }
}
