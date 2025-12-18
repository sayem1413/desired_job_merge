<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans; font-size: 12px; }
        h2 { background: #f2f2f2; padding: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        .strong { color: green; font-weight: bold; }
        .partial { color: orange; }
        .none { color: red; }
    </style>
</head>
<body>

<h1>Desired Job Hierarchy Analysis Report</h1>
<p>Generated at: {{ now() }}</p>

@foreach ($report as $key => $item)

    <h2>Category {{ $key + 1 }}: {{ $item['category'] }}</h2>

    <p>
        Parent Match:
        <strong>{{ $item['parent_match'] ?? 'N/A' }}</strong>
        ({{ $item['parent_percent'] }}%)
        —
        <span class="
            {{ $item['parent_status'] === 'Strong Match' ? 'strong' :
               ($item['parent_status'] === 'Partial Match' ? 'partial' : 'none') }}">
            {{ $item['parent_status'] }}
        </span>
    </p>

    <table>
        <thead>
            <tr>
                <th>SL</th>
                <th>CSV Title</th>
                <th>DB Match</th>
                <th>Match %</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($item['children'] as $childKey => $childValue)
                <tr>
                    <td>{{ $childKey + 1 }}</td>
                    <td>{{ $childValue['csv_title'] }}</td>
                    <td>{{ $childValue['db_match'] ?? 'N/A' }}</td>
                    <td>{{ $childValue['percentage'] }}%</td>
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
