<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagGroupCondition extends Model
{
    use HasFactory;

    protected $fillable = ['tag_group_id', 'tag_id', 'group_index'];

    public function tagGroup()
    {
        return $this->belongsTo(TagGroup::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
