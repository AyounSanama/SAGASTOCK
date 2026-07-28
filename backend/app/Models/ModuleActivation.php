<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ModuleActivation extends Model {
    protected $fillable=['target_type','target_id','module_code','is_enabled','updated_by'];
    protected function casts():array{return ['is_enabled'=>'boolean'];}
}
