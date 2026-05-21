<x-app-layout>
    <x-slot name="header">Benachrichtigungen</x-slot>

    <x-card>
        @if($notifications->isEmpty())
            <x-empty-state icon="bell"
                title="Keine Benachrichtigungen"
                description="Sobald du eine Aufgabe erhaeltst oder eine Freigabe geprueft werden muss, taucht es hier auf." />
        @else
            <div class="mb-3 flex justify-end">
                <form method="POST" action="{{ route('notifications.read_all') }}">
                    @csrf
                    <button class="text-sm text-indigo-600 hover:text-indigo-500">Alle als gelesen markieren</button>
                </form>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach($notifications as $n)
                    <li class="py-3 flex items-start gap-3">
                        <span class="mt-1 inline-block h-2 w-2 rounded-full {{ $n->read_at ? 'bg-slate-300' : 'bg-indigo-500' }}"></span>
                        <div class="flex-1">
                            @if($n->url)
                                <a href="{{ route('notifications.read', $n) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $n->title }}</a>
                            @else
                                <span class="font-medium text-slate-900">{{ $n->title }}</span>
                            @endif
                            @if($n->body)<div class="text-sm text-slate-600">{{ $n->body }}</div>@endif
                            <div class="text-xs text-slate-500 mt-0.5"><x-fmt-date :value="$n->created_at" format="relative" /></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $notifications->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
