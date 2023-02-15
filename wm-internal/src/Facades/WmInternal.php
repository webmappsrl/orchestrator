<?php

namespace Wm\WmInternal\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Wm\WmInternal\WmInternal
 */
class WmInternal extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Wm\WmInternal\WmInternal::class;
    }
}
