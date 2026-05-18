@php
    $hasStatus = (bool) session('status');
    $hasErrors = $errors && $errors->any();
@endphp

@if($hasStatus || $hasErrors)
    <div x-data="{ open: true }" x-init="setTimeout(() => open = false, 6000)" x-show="open" x-transition.duration.300ms
         class="fixed bottom-6 right-6 z-50 max-w-md" style="display:none;">
        @if($hasStatus)
            <div class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-lg">
                <svg class="h-5 w-5 mt-0.5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                <div class="flex-1">{{ session('status') }}</div>
                <button type="button" @click="open = false" class="text-emerald-700 hover:text-emerald-900">✕</button>
            </div>
        @endif
        @if($hasErrors)
            <div class="mt-2 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-lg">
                <svg class="h-5 w-5 mt-0.5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                <div class="flex-1">
                    <strong>{{ $errors->count() === 1 ? 'Es ist ein Fehler aufgetreten' : 'Es sind '.$errors->count().' Fehler aufgetreten' }}</strong>
                    <ul class="mt-1 list-disc ps-5 space-y-0.5 text-xs">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" @click="open = false" class="text-rose-700 hover:text-rose-900">✕</button>
            </div>
        @endif
    </div>
@endif
