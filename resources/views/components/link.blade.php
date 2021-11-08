<!-- /resources/views/components/dashboard/sortable-link.blade.php -->
@props([
'column',
'title',
'type' => 'default',
'request',
'query',
])
@php
/**
 * @var ComponentAttributeBag $attributes
 * @var string $column
 * @var string $title
 * @var string $type
 * @var array $query
 * @var Request $request
 */

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\ComponentAttributeBag;

$container = Container::getInstance();
/** @var Translator $translator */
$translator = $container->get(Translator::class);
if (!(isset($request) && $request instanceof Request)) {
    $request = $container->get(Request::class);
}
$isActive = $request->has('sort') && $request->get('sort') === $column;

$direction = 'asc';
if ($isActive && $request->has('direction')) {
    $direction = $request->get('direction') === 'asc' ? 'desc' : 'asc';
}

$icon = 'bi-arrow-down-up';
$alt = $translator->get('Sort');
if ($isActive) {
    // Direction is the next direction, so current one is the other
    $alt = $direction === 'asc' ? $translator->get('Sort ascending') : $translator->get('Sort descending');
    $icon = match ($type) {
        'numeric', 'alpha' => $direction === 'asc' ? 'bi-sort-' . $type . '-up' : 'bi-sort-' . $type . '-down',
        'default' => $direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down',
    };
}

$question = $request->getBaseUrl() . $request->getPathInfo() === '/' ? '/?' : '?';
$query = array_merge($query ?? [], ['sort' => $column, 'direction' => $direction]);
$url = $request->url() . $question . Arr::query($query);
@endphp
<a {{ $attributes->class([$isActive ? 'link-primary' : 'link-dark']) }} href="{{ $url }}" title="{{ $alt }}">@isset($title){{ $title }}@else{{ ucfirst($column) }}@endisset<span class="ms-1 {!! $icon !!}"></span></a>
