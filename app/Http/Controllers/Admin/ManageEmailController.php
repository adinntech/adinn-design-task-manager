<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllUsersMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Standalone "Manage Email" mailing list — App\Models\User (real login accounts)
 * is never touched here. all_users_mail is a separate, unrelated table used only
 * to collect Name/Email pairs (optionally bulk-imported from an .xlsx during
 * development — see config('features.manage_email_excel_import')).
 */
class ManageEmailController extends Controller
{
    public function index(Request $request): View
    {
        $records = AllUsersMail::query()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.manage-email.index', [
            'records' => $records,
            'excelImportEnabled' => (bool) config('features.manage_email_excel_import'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mail' => ['required', 'email', 'max:255', 'unique:all_users_mail,mail'],
        ], [
            'mail.unique' => 'This email already exists in the list.',
        ]);

        AllUsersMail::create($data);

        return redirect()
            ->route('admin.manage-email.index')
            ->with('success', 'User added successfully.');
    }

    public function edit(AllUsersMail $allUsersMail): View
    {
        return view('admin.manage-email.edit', ['record' => $allUsersMail]);
    }

    public function update(Request $request, AllUsersMail $allUsersMail): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mail' => ['required', 'email', 'max:255', Rule::unique('all_users_mail', 'mail')->ignore($allUsersMail->id)],
        ], [
            'mail.unique' => 'This email already exists in the list.',
        ]);

        $allUsersMail->update($data);

        return redirect()
            ->route('admin.manage-email.index')
            ->with('success', 'Record updated successfully.');
    }

    public function destroy(AllUsersMail $allUsersMail): RedirectResponse
    {
        $allUsersMail->delete();

        return redirect()
            ->route('admin.manage-email.index')
            ->with('success', 'Record deleted successfully.');
    }

    /**
     * Columns (by position, header row skipped): A S.No (ignored), B Name,
     * C Email Address, D Status (ignored). Duplicates (already in the table,
     * or repeated within the same file) are skipped, never overwritten —
     * there is no existing uniqueness rule for this table, so silently
     * updating/replacing a row on re-upload would be surprising.
     */
    public function import(Request $request): RedirectResponse
    {
        abort_unless(config('features.manage_email_excel_import'), 404);

        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $sheet = IOFactory::load($request->file('excel_file')->getRealPath())->getActiveSheet();

        $existingMails = AllUsersMail::query()->pluck('mail')
            ->map(fn ($mail) => strtolower(trim($mail)))
            ->flip();

        $seenInFile = [];
        $toInsert = [];
        $imported = 0;
        $skipped = 0;
        $invalid = 0;
        $now = now();

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowIndex = $row->getRowIndex();
            $name = trim((string) $sheet->getCell([2, $rowIndex])->getValue());
            $email = trim((string) $sheet->getCell([3, $rowIndex])->getValue());

            if ($name === '' && $email === '') {
                continue; // blank trailing row — not real data, not counted
            }

            if ($name === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;

                continue;
            }

            $key = strtolower($email);

            if (isset($existingMails[$key]) || isset($seenInFile[$key])) {
                $skipped++;

                continue;
            }

            $seenInFile[$key] = true;
            $toInsert[] = [
                'name' => $name,
                'mail' => $email,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $imported++;
        }

        if ($toInsert !== []) {
            DB::table('all_users_mail')->insert($toInsert);
        }

        return redirect()
            ->route('admin.manage-email.index')
            ->with('success', "Imported: {$imported} | Skipped (duplicate): {$skipped} | Invalid: {$invalid}");
    }
}
