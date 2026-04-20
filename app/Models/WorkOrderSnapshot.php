<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderSnapshot extends Model
{
    protected $table = 'work_order_snapshots';

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'order_item_no',
        'order_no',
        'work_order_no',
        'current_status',
        'current_stage',
        'current_holder_type',
        'current_holder_id',
        'current_holder_name',
        'linked_special_production_no',
        'next_expected_action',
        'last_event_id',
        'last_changed_at',
        'alert_count',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'last_changed_at' => 'datetime',
        ];
    }
}
