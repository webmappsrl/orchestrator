<?php

if (!function_exists('wrap_and_format_name')) {
    function wrap_and_format_name($name)
    {
        $wrappedName = wordwrap($name, config('orchestrator.utility.word-wrap-length'), "\n", true);
        $htmlName = str_replace("\n", '<br>', $wrappedName);
        return $htmlName;
    }
}
