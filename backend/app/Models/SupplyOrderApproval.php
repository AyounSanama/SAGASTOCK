<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyOrderApproval extends Model
{
    use HasUuids;
    protected $fillable = ['supply_order_id','level','decision','comment','decided_by','decided_at'];
    protected function casts(): array { return ['decided_at'=>'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(SupplyOrder::class, 'supply_order_id'); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
