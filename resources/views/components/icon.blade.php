@props(['name', 'size' => 24, 'strokeWidth' => null])

@php
    // Inline SVG icon component (docs/rewrite/golden-master-review.md,
    // "Follow-up needed" #1). The historical UI used `react-feather` (MIT,
    // resources/icons/LICENSE-feather.txt), one `lucide` glyph (list-ordered),
    // and two custom marks (`nuss`, `nusszopf-header-logo`) — reproduced here as
    // plain SVG files, not an icon font or JS dependency. `size` and
    // `strokeWidth` mirror the historical `<Icon size strokeWidth />` props.
    static $cache = [];
    $svg = $cache[$name] ??= trim(file_get_contents(resource_path("icons/{$name}.svg")));

    $svg = preg_replace('/\swidth="[^"]*"/', ' width="'.e($size).'"', $svg, 1);
    $svg = preg_replace('/\sheight="[^"]*"/', ' height="'.e($size).'"', $svg, 1);
    if ($strokeWidth !== null) {
        $svg = preg_replace('/\sstroke-width="[^"]*"/', ' stroke-width="'.e($strokeWidth).'"', $svg, 1);
    }
    $svg = preg_replace('/\sclass="[^"]*"/', '', $svg, 1);
    $svg = preg_replace('/^<svg/', '<svg aria-hidden="true" focusable="false" '.$attributes->toHtml(), $svg, 1);
@endphp
{!! $svg !!}
