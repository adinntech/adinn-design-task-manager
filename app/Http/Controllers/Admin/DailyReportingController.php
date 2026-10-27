<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllUsersMail;
use App\Models\AppSetting;
use App\Models\DailyReportRun;
use App\Services\DailyDesignerProgressReportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin Daily Reporting — recipient configuration only (To/CC). Nothing is
 * sent from here. Suggestions come from the Manage Email list (all_users_mail),
 * which is read-only to this controller; external addresses are stored only in
 * the setting itself, never added to all_users_mail.
 */
class DailyReportingController extends Controller
{
    public const SETTING_KEY = 'daily_reporting_recipients';

    private const MAX_RECIPIENTS = 50;

    public function index(): View
    {
        $saved = AppSetting::getValue(self::SETTING_KEY, []);

        return view('admin.daily-reporting.index', [
            'to' => $saved['to'] ?? [],
            'cc' => $saved['cc'] ?? [],
            'runs' => DailyReportRun::query()->latest('report_date')->latest('id')->limit(10)->get(),
        ]);
    }

    /** Debounced server-side search — returns only name + mail, max 10 rows. */
    public function recipients(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '' || mb_strlen($query) > 100) {
            return response()->json([]);
        }

        $term = '%'.addcslashes($query, '\\%_').'%';

        return response()->json(
            AllUsersMail::query()
                ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('mail', 'like', $term))
                ->orderBy('name')
                ->limit(10)
                ->get(['name', 'mail'])
                ->map(fn ($row) => ['name' => $row->name, 'mail' => $row->mail])
        );
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'to' => $this->trimmedMails($request->input('to', [])),
            'cc' => $this->trimmedMails($request->input('cc', [])),
        ]);

        $request->validate([
            'to' => ['required', 'array', 'min:1', 'max:'.self::MAX_RECIPIENTS],
            'to.*' => ['required', 'string', 'email:rfc,filter', 'max:255'],
            'cc' => ['nullable', 'array', 'max:'.self::MAX_RECIPIENTS],
            'cc.*' => ['required', 'string', 'email:rfc,filter', 'max:255'],
        ], [
            'to.required' => 'Add at least one To recipient.',
            'to.min' => 'Add at least one To recipient.',
            'to.*.email' => '":input" is not a valid email address.',
            'cc.*.email' => '":input" is not a valid email address.',
        ]);

        $to = $this->uniqueMails($request->input('to', []));
        $cc = $this->uniqueMails($request->input('cc', []));

        $overlap = array_values(array_intersect(array_map('strtolower', $to), array_map('strtolower', $cc)));
        if ($overlap !== []) {
            throw ValidationException::withMessages([
                'cc' => 'These addresses are already in To and cannot also be in CC: '.implode(', ', $overlap).'.',
            ]);
        }

        AppSetting::putValue(self::SETTING_KEY, [
            'to' => $this->withRegisteredNames($to),
            'cc' => $this->withRegisteredNames($cc),
        ], $request->user()->id);

        return redirect()
            ->route('admin.daily-reporting.index')
            ->with('success', 'Daily Reporting recipients saved successfully.');
    }

    /**
     * "Send Mail" — immediate report for today (IST) to the SAVED recipients
     * only; any recipients in the request body are ignored. Recorded as a
     * separate manual run, so tonight's scheduled 23:59 report still goes out.
     */
    public function sendNow(Request $request, DailyDesignerProgressReportService $reports): JsonResponse
    {
        // Serialises double-clicks / two open tabs; outlives the API timeout.
        $lock = Cache::lock('daily-reporting:manual-send', 120);

        if (! $lock->get()) {
            return response()->json(['ok' => false, 'message' => 'A Send Mail request is already in progress. Please wait for it to finish.'], 409);
        }

        try {
            $result = $reports->sendManual($request->user()->id);
        } finally {
            $lock->release();
        }

        $run = $result['run'];
        $ok = $result['outcome'] === 'sent';

        return response()->json([
            'ok' => $ok,
            'message' => $ok
                ? sprintf(
                    'Daily report sent to %d To / %d CC recipient(s). %d of %d designer(s) did not submit any progress today.',
                    count($run->to_recipients ?? []), count($run->cc_recipients ?? []), $run->included_count, $run->designers_evaluated
                )
                : 'Mail was not sent: '.$result['message'],
        ], $ok ? 200 : 422);
    }

    /** @return array<int, string> */
    private function trimmedMails(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn ($mail) => is_string($mail) ? trim($mail) : $mail)
            ->values()
            ->all();
    }

    /** Case-insensitive de-duplication, first occurrence wins. */
    private function uniqueMails(array $mails): array
    {
        return collect($mails)->unique(fn ($mail) => strtolower($mail))->values()->all();
    }

    /**
     * Names are resolved server-side from Manage Email (never trusted from the
     * browser); external addresses keep a null name.
     *
     * @return array<int, array{name: ?string, mail: string}>
     */
    private function withRegisteredNames(array $mails): array
    {
        if ($mails === []) {
            return [];
        }

        $names = AllUsersMail::query()
            ->whereIn('mail', $mails)
            ->get(['name', 'mail'])
            ->mapWithKeys(fn ($row) => [strtolower($row->mail) => $row->name]);

        return array_map(fn ($mail) => ['name' => $names[strtolower($mail)] ?? null, 'mail' => $mail], $mails);
    }
}
