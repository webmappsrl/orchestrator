<?php

namespace App\Actions;

/**
 * @method static \Lorisleiva\Actions\Decorators\JobDecorator|\Lorisleiva\Actions\Decorators\UniqueJobDecorator makeJob(\App\Models\Story $story)
 * @method static \Lorisleiva\Actions\Decorators\UniqueJobDecorator makeUniqueJob(\App\Models\Story $story)
 * @method static \Illuminate\Foundation\Bus\PendingDispatch dispatch(\App\Models\Story $story)
 * @method static \Illuminate\Foundation\Bus\PendingDispatch|\Illuminate\Support\Fluent dispatchIf(bool $boolean, \App\Models\Story $story)
 * @method static \Illuminate\Foundation\Bus\PendingDispatch|\Illuminate\Support\Fluent dispatchUnless(bool $boolean, \App\Models\Story $story)
 * @method static dispatchSync(\App\Models\Story $story)
 * @method static dispatchNow(\App\Models\Story $story)
 * @method static dispatchAfterResponse(\App\Models\Story $story)
 * @method static mixed run(\App\Models\Story $story)
 */
class StoryTimeService
{
}
namespace Lorisleiva\Actions\Concerns;

/**
 * @method void asController()
 */
trait AsController
{
}
/**
 * @method void asListener()
 */
trait AsListener
{
}
/**
 * @method void asJob()
 */
trait AsJob
{
}
/**
 * @method void asCommand(\Illuminate\Console\Command $command)
 */
trait AsCommand
{
}