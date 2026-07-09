<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'movement_uuid',
        'stock_row_no',
        'component_no',
        'department_no',
        'product_no',
        'movement_type',
        'direction',
        'title_human',
        'quantity_before',
        'quantity_delta',
        'quantity_after',
        'buffer_before',
        'buffer_delta',
        'buffer_after',
        'source_type',
        'source_id',
        'order_item_no',
        'order_no',
        'work_order_no',
        'pool_no',
        'personnel_task_no',
        'source_screen',
        'source_action',
        'source_route',
        'actor_type',
        'actor_id',
        'actor_name',
        'actor_department',
        'description',
        'metadata',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'happened_at' => 'datetime',
        ];
    }
}
