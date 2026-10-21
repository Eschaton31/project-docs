<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['disk', 'path', 'original_name', 'mime', 'size', 'uploaded_by'])]
class Asset extends Model {}
