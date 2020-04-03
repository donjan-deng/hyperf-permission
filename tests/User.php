<?php

namespace Donjan\Permission\Test;

use Donjan\Permission\Traits\HasRoles;
use Hyperf\DbConnection\Model\Model;

class User extends Model
{
    use HasRoles;

    protected $fillable = ['email'];

    public $timestamps = false;

    protected $table = 'user';
}
