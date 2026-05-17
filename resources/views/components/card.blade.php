@props(['title' => null, 'description' => null])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white shadow-sm']) }}>
    @if($title || $description)
        <div class="border-b border-slate-200 px-6 py-4">
            @if($title)<h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>@endif
            @if($description)<p class="mt-1 text-sm text-slate-500">{{ $description }}</p>@endif
        </div>
    @endif
    <div class="p-6">
        {{ $slot }}
    </div>
</div>
