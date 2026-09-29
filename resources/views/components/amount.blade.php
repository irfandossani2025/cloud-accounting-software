@props(['value', 'drcr' => false, 'blank' => true])
@php
    $text = $drcr ? \App\Support\Money::drCr($value) : \App\Support\Money::format($value, $blank);
@endphp
<span {{ $attributes->class(['text-red-700' => ! $drcr && $value < 0]) }}>{{ $text }}</span>
