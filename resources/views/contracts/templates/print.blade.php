<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2937; line-height: 1.55; }
        h1 { font-size: 18pt; color: #111827; }
        h2 { font-size: 13pt; color: #111827; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        h3 { font-size: 11pt; color: #374151; }
        p { margin: 0 0 8px 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 4px 6px; border: 1px solid #d1d5db; }
        .footer { margin-top: 22mm; font-size: 8pt; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    {!! $body !!}
    <div class="footer">
        Erzeugt mit Open Workflow Engine · {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>
