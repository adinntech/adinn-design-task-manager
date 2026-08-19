@php
    $initialRows = collect($rows ?? [])
        ->filter(fn ($row) => is_array($row))
        ->map(fn ($row) => [
            'name' => (string) ($row['name'] ?? ''),
            'width' => $row['width'] ?? '',
            'height' => $row['height'] ?? '',
            'ratio' => (string) ($row['ratio'] ?? ''),
        ])
        ->values()
        ->all();

    if ($initialRows === []) {
        $initialRows = [[
            'name' => '',
            'width' => '',
            'height' => '',
            'ratio' => '',
        ]];
    }
@endphp

<div
    x-data='{
        rows: @json($initialRows),
        addRow() {
            this.rows.push({ name: "", width: "", height: "", ratio: "" });
        },
        removeRow(index) {
            if (this.rows.length === 1) {
                this.rows[0] = { name: "", width: "", height: "", ratio: "" };
                return;
            }
            this.rows.splice(index, 1);
        }
    }'
    style="border:1px solid #e4e7ec;border-radius:12px;overflow:hidden;background:#fff"
>
    <div style="overflow-x:auto">
        <table style="width:100%;min-width:700px;border-collapse:collapse;font-size:10px">
            <thead>
                <tr style="background:#f8fafc">
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">Name</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">Width</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">Height</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">Ratio</th>
                    <th style="width:78px;padding:9px 10px;text-align:center;border-bottom:1px solid #e4e7ec">Action</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, index) in rows" :key="index">
                    <tr>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input class="premium-input" type="text" x-model="row.name" :name="`requirements[{{ $fieldKey }}][${index}][name]`" placeholder="e.g. Main Creative">
                        </td>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input class="premium-input" type="number" min="0.01" step="any" x-model="row.width" :name="`requirements[{{ $fieldKey }}][${index}][width]`" placeholder="Width">
                        </td>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input class="premium-input" type="number" min="0.01" step="any" x-model="row.height" :name="`requirements[{{ $fieldKey }}][${index}][height]`" placeholder="Height">
                        </td>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input class="premium-input" type="text" x-model="row.ratio" :name="`requirements[{{ $fieldKey }}][${index}][ratio]`" placeholder="e.g. 16:9">
                        </td>
                        <td style="padding:7px;text-align:center;border-bottom:1px solid #eef0f3">
                            <button type="button" @click="removeRow(index)" style="border:1px solid #fecaca;background:#fff1f2;color:#b42318;border-radius:8px;padding:7px 9px;font-size:14px;font-weight:800;cursor:pointer">×</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;background:#fcfcfd">
        <div class="edit-help" style="margin:0">Add every required creative size with Name, Width, Height and Ratio.</div>
        <button type="button" @click="addRow()" style="border:1px solid #d0d5dd;background:#fff;color:#344054;border-radius:8px;padding:8px 11px;font-size:9px;font-weight:850;cursor:pointer;white-space:nowrap">+ Add New Row</button>
    </div>
</div>

@error('requirements.'.$fieldKey)
    <div class="error">{{ $message }}</div>
@enderror
