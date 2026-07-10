<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandForecastSnapshot extends Model
{
    protected $table = 'demand_forecast_snapshots';

    protected $fillable = [
        'product_id',
        'component_id',
        'period_date',
        'period_type',
        'actual_demand',
        'forecasted_demand',
        'confidence_lower',
        'confidence_upper',
        'mape',
        'status',
        'override_value',
        'approved_by',
        'ai_summary',
        'model_metadata',
    ];

    protected $casts = [
        'period_date' => 'date',
        'forecasted_demand' => 'decimal:2',
        'confidence_lower' => 'decimal:2',
        'confidence_upper' => 'decimal:2',
        'mape' => 'decimal:4',
        'model_metadata' => 'array',
        'override_value' => 'integer',
        'actual_demand' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'No');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'component_id', 'No');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by', 'PersonelNo');
    }

    public function scopePredicted($query)
    {
        return $query->where('status', 'predicted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
