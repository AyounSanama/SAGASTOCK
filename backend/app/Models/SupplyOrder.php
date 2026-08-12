<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyOrder extends Model
{
    use HasUuids;
    protected $fillable = ['organization_id','requesting_site_id','supplying_site_id','offline_uuid','reference','requested_delivery_date','priority','status','required_approval_levels','current_approval_level','notes','rejection_reason','created_by','submitted_by','prepared_by','submitted_at','approved_at','prepared_at','completed_at'];
    protected function casts(): array { return ['requested_delivery_date'=>'date','submitted_at'=>'datetime','approved_at'=>'datetime','prepared_at'=>'datetime','completed_at'=>'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function requestingSite(): BelongsTo { return $this->belongsTo(Site::class, 'requesting_site_id'); }
    public function supplyingSite(): BelongsTo { return $this->belongsTo(Site::class, 'supplying_site_id'); }
    public function lines(): HasMany { return $this->hasMany(SupplyOrderLine::class); }
    public function approvals(): HasMany { return $this->hasMany(SupplyOrderApproval::class)->orderBy('level'); }
}
