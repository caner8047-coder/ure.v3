<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderEvent extends Model
{
    protected $table = 'work_order_events';

    protected $fillable = [
        'event_uuid',
        'correlation_id',
        'aggregate_type',
        'aggregate_id',
        'order_item_no',
        'order_no',
        'work_order_no',
        'pool_no',
        'personnel_task_no',
        'special_production_no',
        'event_type',
        'event_group',
        'source_screen',
        'source_action',
        'source_route',
        'actor_type',
        'actor_id',
        'actor_name',
        'actor_department',
        'status_before',
        'status_after',
        'title_human',
        'summary_human',
        'next_step_human',
        'payload_before',
        'payload_after',
        'context',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_before' => 'array',
            'payload_after' => 'array',
            'context' => 'array',
            'happened_at' => 'datetime',
        ];
    }
}
