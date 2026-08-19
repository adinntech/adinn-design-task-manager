@php
    $rowLabel = $rowLabel ?? 'Board';
    $addLabel = $addLabel ?? ('Add '.$rowLabel);
    $initialRows = collect($rows ?? [])
        ->filter(fn ($row) => is_array($row))
        ->map(fn ($row) => [
            'name' => (string) ($row['name'] ?? ''),
            'width' => $row['width'] ?? '',
            'height' => $row['height'] ?? '',
            'area' => $row['area'] ?? $row['square_feet'] ?? '',
            'unit' => (string) ($row['unit'] ?? 'feet'),
        ])
        ->values()
        ->all();

    if ($initialRows === []) {
        $initialRows = [[
            'name' => '',
            'width' => '',
            'height' => '',
            'area' => '',
            'unit' => 'feet',
        ]];
    }
@endphp

<div
    x-data='{
        rows: @json($initialRows),
        addRow() {
            this.rows.push({ name: "", width: "", height: "", area: "", unit: "feet" });
        },
        removeRow(index) {
            if (this.rows.length === 1) {
                this.rows[0] = { name: "", width: "", height: "", area: "", unit: "feet" };
                return;
            }
            this.rows.splice(index, 1);
        },
        calculateArea(row) {
            const width = parseFloat(row.width);
            const height = parseFloat(row.height);
            if (!Number.isNaN(width) && !Number.isNaN(height)) {
                row.area = Math.round((width * height + Number.EPSILON) * 100) / 100;
            }
        }
    }'
    style="border:1px solid #e4e7ec;border-radius:12px;overflow:hidden;background:#fff"
>
    <div style="overflow-x:auto">
        <table style="width:100%;min-width:720px;border-collapse:collapse;font-size:10px">
            <thead>
                <tr style="background:#f8fafc">
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">{{ $rowLabel }} Name</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">Width</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">Height</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">Area</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #e4e7ec">Unit</th>
                    <th style="width:78px;padding:9px 10px;text-align:center;border-bottom:1px solid #e4e7ec">Action</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, index) in rows" :key="index">
                    <tr>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input
                                class="premium-input"
                                type="text"
                                x-model="row.name"
                                :name="`requirements[{{ $fieldKey }}][${index}][name]`"
                                placeholder="{{ $rowLabel }} name"
                            >
                        </td>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input
                                class="premium-input"
                                type="number"
                                min="0"
                                step="any"
                                x-model="row.width"
                                @input="calculateArea(row)"
                                :name="`requirements[{{ $fieldKey }}][${index}][width]`"
                                placeholder="Width"
                            >
                        </td>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input
                                class="premium-input"
                                type="number"
                                min="0"
                                step="any"
                                x-model="row.height"
                                @input="calculateArea(row)"
                                :name="`requirements[{{ $fieldKey }}][${index}][height]`"
                                placeholder="Height"
                            >
                        </td>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input
                                class="premium-input"
                                type="number"
                                min="0"
                                step="any"
                                x-model="row.area"
                                :name="`requirements[{{ $fieldKey }}][${index}][area]`"
                                placeholder="Area"
                            >
                        </td>
                        <td style="padding:7px;border-bottom:1px solid #eef0f3">
                            <input
                                class="premium-input"
                                type="text"
                                x-model="row.unit"
                                :name="`requirements[{{ $fieldKey }}][${index}][unit]`"
                                placeholder="feet"
                            >
                        </td>
                        <td style="padding:7px;text-align:center;border-bottom:1px solid #eef0f3">
                            <button
                                type="button"
                                @click="removeRow(index)"
                                style="border:1px solid #fecaca;background:#fff1f2;color:#b42318;border-radius:8px;padding:7px 9px;font-size:9px;font-weight:800;cursor:pointer"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;background:#fcfcfd">
        <div class="edit-help" style="margin:0">Width × Height automatically updates Area. You can still correct Area manually.</div>
        <button
            type="button"
            @click="addRow()"
            style="border:1px solid #d0d5dd;background:#fff;color:#344054;border-radius:8px;padding:8px 11px;font-size:9px;font-weight:850;cursor:pointer;white-space:nowrap"
        >
            + {{ $addLabel }}
        </button>
    </div>
</div>

@error('requirements.'.$fieldKey)
    <div class="error">{{ $message }}</div>
@enderror
