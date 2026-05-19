@if(! empty($zugferdData))
@php
    $zfLabels = [
        'invoice_number' => 'Rechnungsnummer',
        'invoice_date' => 'Rechnungsdatum',
        'amount_net' => 'Netto',
        'amount_tax' => 'USt-Betrag',
        'amount_gross' => 'Brutto',
        'currency' => 'Waehrung',
        'vendor_name' => 'Lieferant',
        'vendor_vat_id' => 'USt-IdNr. Lieferant',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'buyer_reference' => 'Leitweg-ID / Buyer Ref',
    ];
    $zfCurrency = $zugferdData['currency'] ?? '';
@endphp
<x-card>
    <div class="flex items-center gap-2 mb-3">
        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">ZUGFeRD / XRechnung</span>
        <span class="text-xs text-slate-500">Strukturierte Daten direkt aus der eingebetteten XML — verbindlich.</span>
    </div>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
        @foreach($zfLabels as $key => $label)
            @if(! empty($zugferdData[$key]))
                <div class="flex justify-between border-b border-slate-100 py-1.5">
                    <dt class="text-slate-500">{{ $label }}</dt>
                    <dd class="font-medium text-slate-900 text-right">
                        @if(in_array($key, ['amount_net', 'amount_tax', 'amount_gross'], true) && is_numeric($zugferdData[$key]))
                            {{ number_format((float) $zugferdData[$key], 2, ',', '.') }} {{ $zfCurrency }}
                        @elseif($key === 'invoice_date')
                            {{ \Carbon\Carbon::parse($zugferdData[$key])->format('d.m.Y') }}
                        @else
                            {{ $zugferdData[$key] }}
                        @endif
                    </dd>
                </div>
            @endif
        @endforeach
    </dl>
</x-card>
@endif
