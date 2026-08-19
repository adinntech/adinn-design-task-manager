@php
    $boardRows = collect($rows ?? [])
        ->filter(fn ($row) => is_array($row))
        ->map(fn ($row) => [
            'name' => (string) ($row['name'] ?? ''),
            'width' => $row['width'] ?? '—',
            'height' => $row['height'] ?? '—',
            'area' => $row['area'] ?? $row['square_feet'] ?? '—',
            'unit' => (string) ($row['unit'] ?? '—'),
        ])
        ->values();
@endphp

<div class="board-table-shell">
    <table class="board-details-table">
        <thead>
            <tr>
                <th>Board Name</th>
                <th>Width</th>
                <th>Height</th>
                <th>Area</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            @forelse($boardRows as $row)
                <tr>
                    <td>{{ $row['name'] !== '' ? $row['name'] : '—' }}</td>
                    <td>{{ $row['width'] }}</td>
                    <td>{{ $row['height'] }}</td>
                    <td>{{ $row['area'] }}</td>
                    <td>{{ ucfirst($row['unit']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="board-table-empty">No board details available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
