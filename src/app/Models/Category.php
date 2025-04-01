<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $guarded = [ //モデルの属性(カラム)をまとめて設定する。
        'id', //idカラムを変更できないようにする。
    ];
}
