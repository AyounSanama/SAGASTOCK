<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Role extends Model {
    protected $fillable = ['code', 'name', 'is_system'];
    protected function casts(): array { return ['is_system' => 'boolean']; }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class)->withPivot(['scope_type', 'scope_id'])->withTimestamps(); }
    public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class); }
}
