<div class="text-center">
    ORCHESTRATOR {{ config('app.release') }} ({{ config('app.release_date') }}) ENV: {{ config('app.env') }}

    <span class="px-1">&middot;</span>
    <a href="https://nova.laravel.com" class="text-primary dim no-underline">Laravel Nova</a>
    v{{ \Laravel\Nova\Nova::version() }}

    <span class="px-1">&middot;</span>
    <a href="https://laravel.com/" class="text-primary dim no-underline">Laravel</a>
    v{{ app()->version() }}

    <span class="px-1">&middot;</span>
    <a href="https://php.net/" class="text-primary dim no-underline">PHP</a>
    v{{ phpversion() }}

    <span class="px-1">&middot;</span>
    &copy; <a class="font-bold text-green-600" target="blank" href="https://webmapp.it/">WEBMAPP</a>

</div>
