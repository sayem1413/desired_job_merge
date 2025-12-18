<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans; font-size: 11px; }
        h2 { margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ccc; padding: 5px; }
        th { background: #f2f2f2; }
        .strong { color: green; font-weight: bold; }
        .partial { color: orange; }
        .none { color: red; }
    </style>
</head>
<body>

<h2>Analysis Report V2</h2>

<p>
    Generated at: {{ $generatedAt->format('Y-m-d H:i:s') }} (Asia/Dhaka)
</p>

@foreach ($report as $key =>$block)

    <h3>
        Category {{ $key + 1 }}: {{ $block['category'] }} <br>
        Parent Match:
        {{ $block['parent']['match'] ?? '—' }}
        ({{ $block['parent']['score'] }}%)
        —
        <span class="
            {{ $block['parent']['status'] === 'Strong Match' ? 'strong' :
               ($block['parent']['status'] === 'Partial Match' ? 'partial' : 'none') }}">
            {{ $block['parent']['status'] }}
        </span>
    </h3>

    <table>
        <thead>
        <tr>
            <th>CSV Title</th>
            <th>DB Match</th>
            <th>Match %</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($block['children'] as $childKey => $childValue)
            <tr>
                <td>{{ $childKey + 1 }}</td>
                <td>{{ $childValue['csv'] }}</td>
                <td>{{ $childValue['match'] ?? '—' }}</td>
                <td>{{ $childValue['score'] }}%</td>
                <td class="
                    {{ $childValue['status'] === 'Strong Match' ? 'strong' :
                        ($childValue['status'] === 'Partial Match' ? 'partial' : 'none') }}">
                    {{ $childValue['status'] }}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

@endforeach

</body>
</html>
