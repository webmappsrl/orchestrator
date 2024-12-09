<?php

namespace App\Services;

abstract class AbstractService
{


  static public function getService(): static
  {
    return app()->make(static::class);
  }
}
