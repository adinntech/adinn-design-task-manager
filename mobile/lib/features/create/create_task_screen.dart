import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';
import '../../core/uploads.dart';
import '../tasks/providers.dart';
import '../widgets/availability_meter.dart';

final taskFormProvider = FutureProvider.autoDispose<TaskFormDef>((ref) => ref.watch(roleApiProvider).taskForm());

/// BD: new task (client or in-house) or draft. Which fields appear — and which are required — comes from the
/// server's form definition, so the app can't drift from the rules that validate the submission.
class CreateTaskScreen extends ConsumerWidget {
  const CreateTaskScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final form = ref.watch(taskFormProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('New task')),
      body: form.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text('$e', textAlign: TextAlign.center),
              const SizedBox(height: 12),
              OutlinedButton(onPressed: () => ref.invalidate(taskFormProvider), child: const Text('Try again')),
            ]),
          ),
        ),
        data: (def) => _CreateForm(def: def),
      ),
    );
  }
}

class _CreateForm extends ConsumerStatefulWidget {
  const _CreateForm({required this.def});

  final TaskFormDef def;

  @override
  ConsumerState<_CreateForm> createState() => _CreateFormState();
}

class _CreateFormState extends ConsumerState<_CreateForm> {
  bool _inhouse = false;
  String? _vertical;
  String? _nature;
  String _partyType = 'client';
  String _priority = 'medium';
  int? _designerId;
  DateTime? _due;
  Availability? _availability;

  final _text = <String, TextEditingController>{};
  final _choices = <String, String?>{};
  final _rows = <String, List<Map<String, String>>>{};
  final _files = <String, List<UploadSource>>{};

  Map<String, String> _errors = {};
  String? _banner;
  bool _busy = false;

  TaskFormDef get def => widget.def;

  TextEditingController _c(String key) => _text.putIfAbsent(key, TextEditingController.new);

  @override
  void dispose() {
    for (final c in _text.values) {
      c.dispose();
    }
    super.dispose();
  }

  List<FormFieldDef> get _dynamicFields => (_vertical == null || _nature == null) ? const [] : def.fieldsFor(_vertical!, _nature!);

  List<Option> get _natureOptions => _vertical == null ? const [] : ((_inhouse ? def.inhouseNatures : def.natures)[_vertical!] ?? const []);

  Future<void> _pickDesigner(int? id) async {
    setState(() {
      _designerId = id;
      _availability = null;
    });
    if (id == null) return;
    try {
      final a = await ref.read(roleApiProvider).availability(id);
      if (mounted && _designerId == id) setState(() => _availability = a);
    } on ApiException {
      // Informational only — never blocks creating a task.
    }
  }

  Future<void> _pickDue() async {
    final now = DateTime.now();
    final last = def.maxDueAt ?? now.add(const Duration(days: 14));
    final picked = await showDatePicker(
      context: context,
      initialDate: _due ?? now,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: last,
    );
    if (picked != null) setState(() => _due = DateTime(picked.year, picked.month, picked.day, 18));
  }

  /// Light client-side checks so obvious gaps don't cost a round trip; the server stays the authority.
  Map<String, String> _validate({required bool draft}) {
    final errors = <String, String>{};
    String v(String k) => _c(k).text.trim();

    if (v('task_name').isEmpty) errors['task_name'] = 'Task name is required';
    if (_vertical == null) errors['vertical'] = 'Choose a vertical';
    if (_nature == null) errors['task_nature'] = 'Choose a task nature';
    if (draft) return errors;

    if (_designerId == null) errors['designer_id'] = 'Choose a designer';
    if ((int.tryParse(v('total_creatives')) ?? 0) < 1) errors['total_creatives'] = 'Enter the number of creatives';

    if (_inhouse) {
      if (v('description').isEmpty) errors['description'] = 'Description is required';
      return errors;
    }

    if (v('party_name').isEmpty) errors['party_name'] = 'Client name is required';
    if (_due == null) errors['due_at'] = 'Choose a due date';
    final mobile = v('mobile_number');
    if (mobile.isNotEmpty && !RegExp(r'^\d{10}$').hasMatch(mobile)) errors['mobile_number'] = 'Enter a 10-digit mobile number';

    for (final f in _dynamicFields.where((f) => f.required || f.either != null)) {
      if (f.type == 'rows') {
        final complete = (_rows[f.key] ?? []).any((r) => f.columns.every((c) => (r[c] ?? '').trim().isNotEmpty));
        final uploaded = f.orUpload != null && (_files[f.orUpload!] ?? []).isNotEmpty;
        if (!complete && !uploaded) errors[f.key] = 'Add at least one complete row${f.orUpload != null ? ' or upload a file' : ''}';
      } else if (f.isFile) {
        final satisfiedByOther = f.either != null && (v(f.either!).isNotEmpty || (_files[f.either!] ?? []).isNotEmpty);
        if (f.required && (_files[f.key] ?? []).isEmpty) errors[f.key] = '${f.label} needs at least one file';
        if (!f.required && f.either != null && (_files[f.key] ?? []).isEmpty && !satisfiedByOther && v('description').isEmpty) {
          errors['description'] = 'Add a description or upload one';
        }
      } else if (f.required && (f.type == 'choice' ? (_choices[f.key] ?? '').isEmpty : v(f.key).isEmpty)) {
        errors[f.key] = '${f.label} is required';
      } else if (!f.required && f.either != null && f.key == 'description' && v('description').isEmpty && (_files[f.either!] ?? []).isEmpty) {
        errors['description'] = 'Add a description or upload one';
      }
    }
    return errors;
  }

  Map<String, dynamic> _collect({required bool draft}) {
    String v(String k) => _c(k).text.trim();
    final values = <String, dynamic>{
      'task_name': v('task_name'),
      'vertical': _vertical,
      'task_nature': _nature,
      if (_designerId != null) 'designer_id': _designerId,
      if (v('total_creatives').isNotEmpty) 'total_creatives': int.tryParse(v('total_creatives')),
    };

    if (_inhouse) {
      values['description'] = v('description');
      if (_nature == 'others') values['task_nature_other'] = v('task_nature_other');
      return values;
    }

    values.addAll({
      'party_type': _partyType,
      'party_name': v('party_name'),
      'priority': _priority,
      if (v('contact_person').isNotEmpty) 'contact_person': v('contact_person'),
      if (v('mobile_number').isNotEmpty) 'mobile_number': v('mobile_number'),
      if (v('zoho_project_number').isNotEmpty) 'zoho_project_number': v('zoho_project_number'),
      if (_due != null) 'due_at': DateFormat('yyyy-MM-dd HH:mm:ss').format(_due!),
    });

    for (final f in _dynamicFields) {
      if (f.isFile) continue;
      if (f.type == 'rows') {
        final rows = (_rows[f.key] ?? []).where((r) => r.values.any((x) => x.trim().isNotEmpty)).toList();
        if (rows.isNotEmpty) values[f.key] = rows;
      } else if (f.type == 'choice') {
        if ((_choices[f.key] ?? '').isNotEmpty) values[f.key] = _choices[f.key];
      } else if (v(f.key).isNotEmpty) {
        values[f.key] = v(f.key);
      }
    }
    return values;
  }

  Future<void> _submit({required bool draft}) async {
    final errors = _validate(draft: draft);
    if (errors.isNotEmpty) {
      setState(() {
        _errors = errors;
        _banner = 'Please fix the highlighted fields.';
      });
      return;
    }

    setState(() {
      _busy = true;
      _errors = {};
      _banner = null;
    });

    try {
      final id = await ref.read(roleApiProvider).submitTask(
            values: _collect(draft: draft),
            files: _files,
            inhouse: _inhouse,
            draft: draft,
          );
      ref.invalidate(boardProvider);
    ref.invalidate(kanbanBoardProvider);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(draft ? 'Saved as draft.' : 'Task created.')));
      draft ? context.pop() : context.pushReplacement('/task/$id');
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _errors = {for (final x in e.fieldErrors.entries) x.key.split('.').first: x.value};
          // Field errors sit next to their fields; the banner only repeats the message when nothing maps to a field.
          _banner = _errors.isNotEmpty ? 'Please fix the highlighted fields.' : e.message;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        SegmentedButton<bool>(
          key: const Key('kind'),
          segments: const [ButtonSegment(value: false, label: Text('Client task')), ButtonSegment(value: true, label: Text('In-house'))],
          selected: {_inhouse},
          onSelectionChanged: (s) => setState(() {
            _inhouse = s.first;
            _nature = null;
            _errors = {};
            _banner = null;
          }),
        ),
        const SizedBox(height: 16),
        if (_banner != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(_banner!, key: const Key('form-banner'), style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ),
        _text_('task_name', 'Task name'),
        _dropdown(
          'vertical',
          'Vertical',
          {for (final o in def.verticals) o.key: o.label},
          _vertical,
          (v) => setState(() {
            _vertical = v;
            _nature = null;
          }),
        ),
        _dropdown('task_nature', 'Task nature', {for (final o in _natureOptions) o.key: o.label}, _nature, (v) => setState(() => _nature = v)),
        if (_inhouse && _nature == 'others') _text_('task_nature_other', 'Describe the nature'),
        if (!_inhouse) ..._clientFields(),
        _designerField(),
        _text_('total_creatives', 'Total creatives', keyboard: TextInputType.number),
        if (_inhouse) ...[
          _text_('description', 'Description', lines: 4),
          _fileField('attachments', 'Attachments'),
        ],
        if (!_inhouse && _nature != null) ..._dynamicFields.expand(_dynamicWidget),
        const SizedBox(height: 8),
        Row(children: [
          Expanded(
            child: OutlinedButton(key: const Key('save-draft'), onPressed: _busy ? null : () => _submit(draft: true), child: const Text('Save draft')),
          ),
          const SizedBox(width: 12),
          Expanded(
            flex: 2,
            child: FilledButton(
              key: const Key('create-task'),
              onPressed: _busy ? null : () => _submit(draft: false),
              child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Create task'),
            ),
          ),
        ]),
        const SizedBox(height: 24),
      ],
    );
  }

  List<Widget> _clientFields() => [
        _dropdown('party_type', 'Party type', const {'client': 'Client', 'agency': 'Agency'}, _partyType, (v) => setState(() => _partyType = v ?? 'client')),
        _clientName(),
        _text_('contact_person', 'Contact person'),
        _text_('mobile_number', 'Mobile number', keyboard: TextInputType.phone),
        _text_('zoho_project_number', 'Zoho project number (optional)'),
        _dropdown('priority', 'Priority', {for (final p in def.priorities) p: p[0].toUpperCase() + p.substring(1)}, _priority, (v) => setState(() => _priority = v ?? 'medium')),
        _pad(InkWell(
          key: const Key('due-date'),
          onTap: _pickDue,
          child: InputDecorator(
            decoration: InputDecoration(labelText: 'Due date', errorText: _errors['due_at'], suffixIcon: const Icon(Icons.calendar_today)),
            child: Text(_due == null ? 'Choose a date' : DateFormat('d MMM yyyy').format(_due!)),
          ),
        )),
      ];

  Widget _pad(Widget child) => Padding(padding: const EdgeInsets.only(bottom: 12), child: child);

  Widget _text_(String key, String label, {int lines = 1, TextInputType? keyboard}) => _pad(TextField(
        key: Key('f-$key'),
        controller: _c(key),
        minLines: lines,
        maxLines: lines > 1 ? lines + 2 : 1,
        keyboardType: keyboard,
        textCapitalization: lines > 1 ? TextCapitalization.sentences : TextCapitalization.none,
        decoration: InputDecoration(labelText: label, errorText: _errors[key], alignLabelWithHint: lines > 1),
      ));

  Widget _dropdown(String key, String label, Map<String, String> options, String? value, ValueChanged<String?> onChanged) => _pad(DropdownButtonFormField<String>(
        key: Key('f-$key-${options.keys.join(',')}'),
        initialValue: options.containsKey(value) ? value : null,
        isExpanded: true,
        decoration: InputDecoration(labelText: label, errorText: _errors[key]),
        items: [for (final o in options.entries) DropdownMenuItem(value: o.key, child: Text(o.value, overflow: TextOverflow.ellipsis))],
        onChanged: onChanged,
      ));

  Widget _clientName() => _pad(Autocomplete<ClientSuggestion>(
        displayStringForOption: (c) => c.name,
        optionsBuilder: (value) async {
          if (value.text.trim().length < 2) return const [];
          try {
            return await ref.read(roleApiProvider).searchClients(value.text.trim());
          } on ApiException {
            return const [];
          }
        },
        onSelected: (c) {
          _c('party_name').text = c.name;
          if ((c.contact ?? '').isNotEmpty) _c('contact_person').text = c.contact!;
          if ((c.mobile ?? '').isNotEmpty) _c('mobile_number').text = c.mobile!;
          setState(() {});
        },
        fieldViewBuilder: (context, controller, focus, onSubmit) {
          return TextField(
            key: const Key('f-party_name'),
            controller: controller,
            focusNode: focus,
            // Our own controller is the single place the value is read from.
            onChanged: (v) => _c('party_name').text = v,
            decoration: InputDecoration(labelText: 'Client name', errorText: _errors['party_name']),
          );
        },
      ));

  Widget _designerField() => _pad(Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        DropdownButtonFormField<int>(
          key: const Key('f-designer_id'),
          initialValue: _designerId,
          isExpanded: true,
          decoration: InputDecoration(labelText: 'Assign to designer', errorText: _errors['designer_id']),
          items: [for (final d in def.designers) DropdownMenuItem(value: d.id, child: Text(d.name, overflow: TextOverflow.ellipsis))],
          onChanged: _pickDesigner,
        ),
        if (_availability != null) AvailabilityMeter(availability: _availability!),
      ]));

  Iterable<Widget> _dynamicWidget(FormFieldDef f) {
    // Upload companions of a rows field / either-or field are rendered by their owner.
    final companions = {for (final x in _dynamicFields) ?x.orUpload};
    if (companions.contains(f.key)) return const [];

    final label = f.required ? '${f.label} *' : f.label;

    switch (f.type) {
      case 'rows':
        return [_rowsEditor(f)];
      case 'file':
      case 'audio':
        return [_fileField(f.key, label)];
      case 'choice':
        return [_dropdown(f.key, label, {for (final o in f.options) o: o}, _choices[f.key], (v) => setState(() => _choices[f.key] = v))];
      case 'multiline':
        return [_text_(f.key, label, lines: 3)];
      case 'integer':
      case 'number':
      case 'phone':
        return [_text_(f.key, label, keyboard: TextInputType.number)];
      case 'email':
        return [_text_(f.key, label, keyboard: TextInputType.emailAddress)];
      default:
        return [_text_(f.key, label)];
    }
  }

  Widget _rowsEditor(FormFieldDef f) {
    final rows = _rows.putIfAbsent(f.key, () => [for (var i = 0; i < 1; i++) {for (final c in f.columns) c: ''}]);

    return _pad(Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${f.label}${f.required ? ' *' : ''}', style: Theme.of(context).textTheme.titleSmall),
          for (var i = 0; i < rows.length; i++)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Row(children: [
                for (final c in f.columns)
                  Expanded(
                    flex: c == 'name' ? 3 : 2,
                    child: Padding(
                      padding: const EdgeInsets.only(right: 6),
                      child: TextField(
                        key: Key('row-${f.key}-$i-$c'),
                        keyboardType: c == 'name' || c == 'ratio' ? TextInputType.text : const TextInputType.numberWithOptions(decimal: true),
                        decoration: InputDecoration(labelText: c[0].toUpperCase() + c.substring(1), isDense: true),
                        onChanged: (v) => rows[i][c] = v,
                      ),
                    ),
                  ),
                IconButton(
                  tooltip: 'Remove row',
                  icon: const Icon(Icons.close),
                  onPressed: rows.length == 1 ? null : () => setState(() => rows.removeAt(i)),
                ),
              ]),
            ),
          TextButton.icon(
            key: Key('add-row-${f.key}'),
            icon: const Icon(Icons.add),
            label: const Text('Add row'),
            onPressed: () => setState(() => rows.add({for (final c in f.columns) c: ''})),
          ),
          if (f.orUpload != null) _fileField(f.orUpload!, 'Or upload a file'),
          if (_errors[f.key] != null) Text(_errors[f.key]!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        ]),
      ),
    ));
  }

  Widget _fileField(String key, String label) {
    final files = _files.putIfAbsent(key, () => []);

    return _pad(Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      OutlinedButton.icon(
        key: Key('pick-$key'),
        icon: const Icon(Icons.attach_file),
        label: Text(label),
        onPressed: () async {
          final picked = await ref.read(filePickerProvider).pick();
          if (picked.isNotEmpty) setState(() => files.addAll(picked));
        },
      ),
      for (final s in List.of(files))
        ListTile(
          dense: true,
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.insert_drive_file_outlined),
          title: Text(s.name, overflow: TextOverflow.ellipsis),
          trailing: IconButton(tooltip: 'Remove', icon: const Icon(Icons.close), onPressed: () => setState(() => files.remove(s))),
        ),
      if (_errors[key] != null) Text(_errors[key]!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
    ]));
  }
}
