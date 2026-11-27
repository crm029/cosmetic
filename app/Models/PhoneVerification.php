<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PhoneVerification extends Model {
 protected $fillable=['mobile','code_hash','expires_at','attempts','consumed_at'];
 protected function casts(): array{return ['expires_at'=>'datetime','consumed_at'=>'datetime','attempts'=>'integer'];}
}