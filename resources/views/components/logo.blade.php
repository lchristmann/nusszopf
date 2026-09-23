@props(['name'])

@php
    // The historical `assets/logos/*.svg` files, inlined the way
    // `babel-plugin-inline-react-svg` did: the component's props (class,
    // title, aria-label) land on the <svg> element itself. Partner logos live
    // in resources/logos (decision A-5); the Nusszopf marks are shared with
    // <x-icon> in resources/icons.
    static $cache = [];
    $path = is_file(resource_path("logos/{$name}.svg")) ? resource_path("logos/{$name}.svg") : resource_path("icons/{$name}.svg");
    $svg = $cache[$name] ??= trim(file_get_contents($path));
    $svg = preg_replace('/^<svg/', '<svg '.$attributes->toHtml(), $svg, 1);
@endphp
{!! $svg !!}
