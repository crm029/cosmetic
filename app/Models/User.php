<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
class User extends Authenticatable {
 use HasFactory,Notifiable;
 protected $fillable=['name','email','mobile','password','is_admin'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_admin'=>'boolean','loyalty_points'=>'integer','totp_secret'=>'encrypted','totp_enabled_at'=>'datetime','totp_last_used_step'=>'integer']; }
 public function loyaltyTransactions(): HasMany { return $this->hasMany(LoyaltyPointTransaction::class)->latest(); }
}