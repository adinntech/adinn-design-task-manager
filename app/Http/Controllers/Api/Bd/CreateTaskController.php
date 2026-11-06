<?php

namespace App\Http\Controllers\Api\Bd;

use App\Http\Controllers\Bd\TaskController as WebTaskController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\In;
use ReflectionClass;
use ReflectionMethod;

/**
 * BD task creation for the mobile app.
 *
 * The create/draft rules are ~1,800 lines of per-vertical validation inside Bd\TaskController, and they must
 * never drift between web and app. So this controller does not re-implement them: it runs the very same
 * controller methods and translates their redirect answers into JSON. Validation errors come back as the
 * standard 422 JSON; `form()` derives the form definition from the same rules.
 */
class CreateTaskController extends Controller
{
    /** Row-editor fields and the upload that may stand in for complete rows. */
    private const ROW_FIELDS = [
        'dimension_rows' => ['upload' => 'dimension_upload', 'columns' => ['name', 'width', 'height']],
        'media_size_rows' => ['upload' => null, 'columns' => ['name', 'width', 'height', 'ratio']],
        'size_rows' => ['upload' => 'size_upload', 'columns' => ['name', 'width', 'height']],
    ];

    public function form(Request $request): JsonResponse
    {
        $web = new WebTaskController;
        $natures = $this->constant('NATURES');
        $fileFields = $this->constant('FILE_FIELDS');

        $combos = [];
        foreach ($natures as $vertical => $list) {
            foreach ($list as $nature) {
                $combos[$vertical][$nature] = $this->fieldsFor($web, $vertical, $nature, $fileFields);
            }
        }

        return response()->json([
            'verticals' => collect(WebTaskController::VERTICALS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'natures' => collect($natures)->map(fn ($list) => collect($list)->map(fn ($n) => ['key' => $n, 'label' => Str::headline($n)])->values()),
            'inhouse_natures' => collect(WebTaskController::inhouseNatures())->map(
                fn ($list) => collect($list)->map(fn ($n) => ['key' => $n, 'label' => Str::headline($n)])->values()
            ),
            'fields' => $combos,
            'priorities' => ['low', 'medium', 'high', 'urgent'],
            'party_types' => ['client', 'agency'],
            'max_due_at' => $this->callProtected($web, 'maximumAllowedDueDate')->toIso8601String(),
            'designers' => User::where('role', 'designer')->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'experienced_verticals', 'skills']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->delegate($request, fn (WebTaskController $web) => $web->store($request), 'task');
    }

    public function storeInhouse(Request $request): JsonResponse
    {
        return $this->delegate($request, fn (WebTaskController $web) => $web->storeInhouse($request), 'task');
    }

    /** Saves or updates (when `draft_id` is sent) a client draft; only the task name/vertical/nature are required. */
    public function storeDraft(Request $request): JsonResponse
    {
        return $this->delegate($request, fn (WebTaskController $web) => $web->storeDraft($request), 'draft');
    }

    public function storeInhouseDraft(Request $request): JsonResponse
    {
        return $this->delegate($request, fn (WebTaskController $web) => $web->storeInhouseDraft($request), 'draft');
    }

    /** Runs the web action and turns its redirect into `{id, message}` — or a 422 if it redirected back with an error. */
    private function delegate(Request $request, Closure $action, string $kind): JsonResponse
    {
        $response = $action(new WebTaskController);

        if ($response instanceof JsonResponse) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            $target = $response->getTargetUrl();

            $id = $kind === 'task'
                ? (preg_match('#/bd/tasks/(\d+)(?:\?|$)#', $target, $m) ? (int) $m[1] : null)
                : (preg_match('#[?&]draft=(\d+)#', $target, $m) ? (int) $m[1] : null);

            if ($id !== null) {
                return response()->json([
                    'id' => $id,
                    'message' => $kind === 'task' ? 'Task created.' : 'Saved as draft.',
                ], 201);
            }

            // The web action bounced back with `errors` (e.g. a file could not be stored).
            $errors = $response->getSession()?->get('errors');
            $message = $errors?->first() ?: 'The request could not be completed. Please try again.';

            return response()->json(['message' => $message, 'errors' => ['upload' => [$message]]], 422);
        }

        return response()->json(['message' => 'Unexpected response.'], 500);
    }

    /**
     * What the form must ask for a vertical + nature: the fields that are required, with enough type
     * information to render them. Anything the server would reject is therefore asked for up front.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fieldsFor(WebTaskController $web, string $vertical, string $nature, array $fileFields): array
    {
        $rules = $this->callProtected($web, 'requirementRules', Request::create('/', 'POST', ['vertical' => $vertical, 'task_nature' => $nature]));

        $fields = [];
        $rowKey = match (true) {
            $vertical === 'outdoor' => 'dimension_rows',
            $vertical === 'media' => 'media_size_rows',
            $vertical === 'pop_offsets' && in_array($nature, ['design_adaptation', 'creative_design'], true) => 'size_rows',
            default => null,
        };

        foreach ($rules as $key => $ruleSet) {
            if (str_contains($key, '.') || ! is_array($ruleSet)) {
                continue;
            }

            $required = $this->isRequired($ruleSet);
            $isRows = isset(self::ROW_FIELDS[$key]);

            if (! $required && ! ($isRows && $key === $rowKey)) {
                continue;
            }

            if ($isRows && $key !== $rowKey) {
                continue;
            }

            $field = ['key' => $key, 'label' => Str::headline($key), 'required' => true] + $this->describe($key, $ruleSet, $fileFields);

            // Road Show: a written description OR an uploaded one satisfies the rule.
            if ($key === 'description' && $vertical === 'roadshow') {
                $field['required'] = false;
                $field['either'] = 'description_upload';
            }
            if ($key === 'description_upload') {
                $field['required'] = false;
                $field['either'] = 'description';
            }

            if ($isRows) {
                $field['columns'] = self::ROW_FIELDS[$key]['columns'];
                $field['or_upload'] = self::ROW_FIELDS[$key]['upload'];
            }

            $fields[] = $field;
        }

        // Always offer these on every combination; the rules treat them as optional free text.
        foreach (['description', 'other_details'] as $optional) {
            if (! collect($fields)->contains('key', $optional)) {
                $fields[] = ['key' => $optional, 'label' => Str::headline($optional), 'type' => 'multiline', 'required' => false];
            }
        }

        return $fields;
    }

    /** @param array<int, mixed> $ruleSet */
    private function isRequired(array $ruleSet): bool
    {
        // `required`, or Road Show's `nullable` + `required_without:other_field` (either one satisfies it).
        foreach ($ruleSet as $i => $rule) {
            if (is_string($rule) && (($i === 0 && $rule === 'required') || str_starts_with($rule, 'required_without'))) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $ruleSet */
    private function describe(string $key, array $ruleSet, array $fileFields): array
    {
        if (isset(self::ROW_FIELDS[$key])) {
            return ['type' => 'rows'];
        }

        if (in_array($key, $fileFields, true)) {
            return ['type' => $key === 'client_audio' ? 'audio' : 'file'];
        }

        $strings = array_filter($ruleSet, 'is_string');
        $in = collect($ruleSet)->first(fn ($r) => $r instanceof In);

        if ($in !== null) {
            $values = str_getcsv(substr((string) $in, 3), ',', '"', '\\');

            return ['type' => 'choice', 'options' => array_values($values)];
        }

        $has = fn (string $needle) => collect($strings)->contains(fn ($r) => $r === $needle || str_starts_with($r, $needle.':'));

        return match (true) {
            $has('integer') => ['type' => 'integer'],
            $has('numeric') => ['type' => 'number'],
            $has('email') => ['type' => 'email'],
            $has('url') => ['type' => 'url'],
            $has('digits') => ['type' => 'phone'],
            $this->maxLength($strings) >= 1000 => ['type' => 'multiline'],
            default => ['type' => 'text'],
        };
    }

    private function maxLength(array $strings): int
    {
        foreach ($strings as $rule) {
            if (str_starts_with($rule, 'max:')) {
                return (int) substr($rule, 4);
            }
        }

        return 0;
    }

    private function constant(string $name): array
    {
        return (new ReflectionClass(WebTaskController::class))->getConstant($name);
    }

    private function callProtected(object $object, string $method, mixed ...$args): mixed
    {
        $m = new ReflectionMethod($object, $method);
        $m->setAccessible(true);

        return $m->invoke($object, ...$args);
    }
}
