<x-mail::message-layout>
    <p>Hallo {{ $recipient->name }},</p>
    <div style="white-space:pre-wrap;">{{ $bodyText }}</div>
    <p style="margin-top:24px;color:#64748b;font-size:12px;">Workflow: {{ $instance->workflow->name }}</p>
</x-mail::message-layout>
