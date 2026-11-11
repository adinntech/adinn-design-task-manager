import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';
import '../../core/server_settings.dart';
import '../../core/theme.dart';
import '../../core/uploads.dart';
import '../tasks/providers.dart';
import '../widgets/availability_meter.dart';
import 'create_task_widgets.dart';

final taskFormProvider = FutureProvider.autoDispose<TaskFormDef>((ref) => ref.watch(roleApiProvider).taskForm());

/// BD: new task (client or in-house), clone of an earlier ticket, or a saved draft being finished. It is the web form: which
/// fields appear (sections, optional fields, uploads, vehicle pictures, size tables) comes from the server's own form
/// definition, and which are required is checked here as on the web and again by the server, so the app can't drift.
class CreateTaskScreen extends ConsumerWidget {
  const CreateTaskScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final form = ref.watch(taskFormProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF3F4F8),
      appBar: AppBar(
        title: const Text('New task', style: TextStyle(fontWeight: FontWeight.w800)),
        backgroundColor: Colors.white,
      ),
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
  DateTime? _due; // the day
  TimeOfDay _dueTime = const TimeOfDay(hour: 18, minute: 0);
  String? _creativeType;
  Availability? _availability;

  final _text = <String, TextEditingController>{};
  final _focus = <String, FocusNode>{};
  final _choices = <String, String?>{};
  final _rows = <String, List<Map<String, String>>>{};
  final _files = <String, List<UploadSource>>{};

  /// Files already on the server: from a draft being finished or a cloned ticket.
  final _kept = <String, List<StoredFile>>{};

  /// Draft files the user removed (paths, by field), sent so the server deletes them.
  final _removed = <String, List<String>>{};

  int? _draftId;
  int? _cloneSourceId;
  String? _prefillLabel;

  // "Clone existing ticket" panel
  bool _cloneOpen = false;
  final _cloneQuery = TextEditingController();
  List<CloneHit> _cloneHits = const [];
  Timer? _cloneDebounce;
  bool _cloneBusy = false;
  int _cloneSeq = 0;

  Map<String, String> _errors = {};
  String? _banner;
  bool _busy = false;

  TaskFormDef get def => widget.def;

  TextEditingController _c(String key) => _text.putIfAbsent(key, TextEditingController.new);

  FocusNode _f(String key) => _focus.putIfAbsent(key, FocusNode.new);

  @override
  void dispose() {
    for (final c in _text.values) {
      c.dispose();
    }
    for (final f in _focus.values) {
      f.dispose();
    }
    _cloneQuery.dispose();
    _cloneDebounce?.cancel();
    super.dispose();
  }

  bool get _digital => _vertical == 'digital_inhouse';

  List<FormFieldDef> get _dynamicFields => (_vertical == null || _nature == null) ? const [] : def.fieldsFor(_vertical!, _nature!);

  List<Option> get _natureOptions => _vertical == null ? const [] : ((_inhouse ? def.inhouseNatures : def.natures)[_vertical!] ?? const []);

  String _labelOf(List<Option> options, String? key) => options.where((o) => o.key == key).map((o) => o.label).firstOrNull ?? '';

  String get _partyLabel => _partyType == 'agency' ? 'Agency' : 'Client';

  /// Total creatives is hidden on Digital / Inhouse until a creative type is chosen (as on the web).
  bool get _showCreatives => !( !_inhouse && _digital && (_creativeType ?? '').isEmpty);

  // ---------------------------------------------------------------------------------------------------------------------
  // Designer, due date

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
      // Informational only: never blocks creating a task.
    }
  }

  Future<void> _pickDue() async {
    final now = DateTime.now();
    final last = def.maxDueAt ?? now.add(const Duration(days: 14));
    final picked = await showDatePicker(context: context, initialDate: _due ?? now, firstDate: DateTime(now.year, now.month, now.day), lastDate: last);
    if (picked == null) return;
    setState(() {
      _due = DateTime(picked.year, picked.month, picked.day);
      // Today, but the usual 6 PM has already gone: move the time to the next hour rather than make the user fix it.
      final at = _dueAt!;
      if (at.isBefore(now)) {
        final next = now.add(const Duration(hours: 1));
        _dueTime = next.day == now.day ? TimeOfDay(hour: next.hour, minute: 0) : const TimeOfDay(hour: 23, minute: 59);
      }
    });
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(context: context, initialTime: _dueTime);
    if (picked != null) setState(() => _dueTime = picked);
  }

  DateTime? get _dueAt => _due == null ? null : DateTime(_due!.year, _due!.month, _due!.day, _dueTime.hour, _dueTime.minute);

  // ---------------------------------------------------------------------------------------------------------------------
  // Validation (the web form's rules; the server stays the authority)

  Map<String, String> _validate({required bool draft}) {
    final errors = <String, String>{};
    String v(String k) => _c(k).text.trim();

    if (v('task_name').isEmpty) errors['task_name'] = 'Task name is required';
    if (_vertical == null) errors['vertical'] = 'Choose a vertical';
    if (_nature == null) errors['task_nature'] = 'Choose a task nature';
    if (draft) return errors;

    if (_inhouse && _nature == 'others' && v('task_nature_other').isEmpty) errors['task_nature_other'] = 'Describe the nature';
    if (_designerId == null) errors['designer_id'] = 'Choose a designer';
    if (!_inhouse && _digital && (_creativeType ?? '').isEmpty) errors['creative_type'] = 'Choose a creative type';
    if (_showCreatives) {
      final n = int.tryParse(v('total_creatives')) ?? 0;
      if (n < 1) {
        errors['total_creatives'] = 'Enter the number of creatives';
      } else if (n > 9999) {
        errors['total_creatives'] = 'At most 9999';
      }
    }

    if (_inhouse) {
      if (v('description').isEmpty) errors['description'] = 'Description is required';
      return errors;
    }

    if (v('party_name').isEmpty) errors['party_name'] = '$_partyLabel name is required';
    if (_due == null) {
      errors['due_at'] = 'Choose a due date';
    } else {
      final at = _dueAt!;
      if (at.isBefore(DateTime.now().subtract(const Duration(minutes: 1)))) errors['due_at'] = 'The due date and time cannot be in the past';
      final max = def.maxDueAt;
      if (max != null && at.isAfter(max)) errors['due_at'] = 'The due date is too far ahead (the latest allowed is ${DateFormat('d MMM yyyy').format(max)})';
    }
    final mobile = v('mobile_number');
    if (mobile.isNotEmpty && !RegExp(r'^\d{10}$').hasMatch(mobile)) errors['mobile_number'] = 'Enter a 10-digit mobile number';

    for (final f in _dynamicFields) {
      if (f.isSection) continue;
      final has = (_files[f.key] ?? []).isNotEmpty || (_kept[f.key] ?? []).isNotEmpty;

      if (f.isRows) {
        final rows = _rows[f.rowsKey] ?? [];
        final complete = rows.any((r) => f.columns.every((c) => (r[c] ?? '').trim().isNotEmpty));
        final partial = rows.any((r) => f.columns.any((c) => (r[c] ?? '').trim().isNotEmpty) && !f.columns.every((c) => (r[c] ?? '').trim().isNotEmpty));
        final uploaded = f.orUpload != null && ((_files[f.orUpload!] ?? []).isNotEmpty || (_kept[f.orUpload!] ?? []).isNotEmpty);
        if (partial) {
          errors[f.rowsKey] = 'Complete ${f.columns.map((c) => c[0].toUpperCase() + c.substring(1)).join(', ')} for every row, or remove the incomplete row.';
        } else if (f.required && !complete && !uploaded) {
          errors[f.rowsKey] = 'Add at least one complete row${f.orUpload != null ? ' or upload a file' : ''}';
        }
      } else if (f.isFile) {
        final satisfiedByOther = f.either != null && (v(f.either!).isNotEmpty || (_files[f.either!] ?? []).isNotEmpty);
        if (f.required && !has) errors[f.key] = '${f.label} needs at least one file';
        if (!f.required && f.either != null && !has && !satisfiedByOther && v('description').isEmpty) errors['description'] = 'Add a description or upload one';
      } else if (f.type == 'select' || f.type == 'vehicle_select') {
        final value = _choices[f.key] ?? '';
        if (f.required && value.isEmpty) {
          errors[f.key] = '${f.label} is required';
        } else if (value == 'Other' && v('${f.key}_other').isEmpty) {
          errors['${f.key}_other'] = 'Specify the other option';
        }
      } else {
        final value = v(f.key);
        if (f.required && value.isEmpty) {
          errors[f.key] = '${f.label} is required';
        } else if (!f.required && f.either != null && f.key == 'description' && value.isEmpty && (_files[f.either!] ?? []).isEmpty) {
          errors['description'] = 'Add a description or upload one';
        } else if (value.isNotEmpty) {
          if (f.type == 'url' && !RegExp(r'^https?://\S+\.\S+', caseSensitive: false).hasMatch(value)) errors[f.key] = 'Enter a full link starting with http:// or https://';
          if (f.type == 'email' && !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(value)) errors[f.key] = 'Enter a valid email address';
          if (f.type == 'number') {
            final n = int.tryParse(value) ?? 0;
            if (n < 1 || n > 9999) errors[f.key] = 'Enter a whole number from 1 to 9999';
          }
          if (f.key == 'creative_mobile_number' && !RegExp(r'^\d{10}$').hasMatch(value)) errors[f.key] = 'Enter a 10-digit mobile number';
        }
      }
    }
    return errors;
  }

  // ---------------------------------------------------------------------------------------------------------------------
  // What is sent

  Map<String, dynamic> _collect({required bool draft}) {
    String v(String k) => _c(k).text.trim();
    final values = <String, dynamic>{
      'task_name': v('task_name'),
      'vertical': _vertical,
      'task_nature': _nature,
      if (_designerId != null) 'designer_id': _designerId,
      if (_showCreatives && v('total_creatives').isNotEmpty) 'total_creatives': int.tryParse(v('total_creatives')),
    };

    if (_draftId != null) values['draft_id'] = _draftId;
    final removed = {for (final e in _removed.entries) if (e.value.isNotEmpty) e.key: e.value};
    if (removed.isNotEmpty) values['removed_files_json'] = jsonEncode(removed);

    // Files of a cloned ticket that were kept are copied on the server into the new task.
    if (_cloneSourceId != null) {
      final kept = {for (final e in _kept.entries) if (e.value.isNotEmpty) e.key: [for (final f in e.value) f.path]};
      values['cloned_from_task_id'] = _cloneSourceId;
      values['cloned_source_files_json'] = jsonEncode(kept);
    }

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
      if (_dueAt != null) 'due_at': DateFormat('yyyy-MM-dd HH:mm:ss').format(_dueAt!),
      if (_digital && (_creativeType ?? '').isNotEmpty) 'creative_type': _creativeType,
    });

    for (final f in _dynamicFields) {
      if (f.isSection || f.isFile) continue;
      if (f.isRows) {
        final rows = [
          for (final r in _rows[f.rowsKey] ?? <Map<String, String>>[])
            if (f.columns.any((c) => (r[c] ?? '').trim().isNotEmpty)) {for (final c in f.columns) c: (r[c] ?? '').trim()},
        ];
        if (rows.isNotEmpty) values[f.rowsKey] = rows;
      } else if (f.type == 'select' || f.type == 'vehicle_select') {
        final value = _choices[f.key] ?? '';
        if (value.isNotEmpty) values[f.key] = value;
        if (value == 'Other' && v('${f.key}_other').isNotEmpty) values['${f.key}_other'] = v('${f.key}_other');
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
      final id = await ref.read(roleApiProvider).submitTask(values: _collect(draft: draft), files: _files, inhouse: _inhouse, draft: draft);
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

  // ---------------------------------------------------------------------------------------------------------------------
  // Clone an earlier ticket / resume a draft

  void _onCloneQuery(String q) {
    _cloneDebounce?.cancel();
    if (q.trim().length < 2) {
      setState(() => _cloneHits = const []);
      return;
    }
    _cloneDebounce = Timer(const Duration(milliseconds: 300), () async {
      final seq = ++_cloneSeq;
      setState(() => _cloneBusy = true);
      try {
        final hits = await ref.read(roleApiProvider).cloneSearch(q.trim(), inhouse: _inhouse);
        if (mounted && seq == _cloneSeq) setState(() => _cloneHits = hits);
      } on ApiException {
        if (mounted && seq == _cloneSeq) setState(() => _cloneHits = const []);
      } finally {
        if (mounted && seq == _cloneSeq) setState(() => _cloneBusy = false);
      }
    });
  }

  Future<void> _useClone(CloneHit hit) async {
    setState(() => _cloneBusy = true);
    try {
      final data = await ref.read(roleApiProvider).cloneData(hit.id);
      if (!mounted) return;
      _apply(data, label: 'Cloned from ${hit.taskId.isEmpty ? hit.name : hit.taskId}');
      setState(() {
        _cloneOpen = false;
        _cloneHits = const [];
        _cloneQuery.clear();
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _banner = e.message);
    } finally {
      if (mounted) setState(() => _cloneBusy = false);
    }
  }

  Future<void> _openDrafts() async {
    final picked = await showModalBottomSheet<DraftSummary>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (_) => const _DraftsSheet(),
    );
    if (picked == null || !mounted) return;
    setState(() => _busy = true);
    try {
      final data = await ref.read(roleApiProvider).draft(picked.id);
      if (mounted) _apply(data, label: 'Draft: ${picked.name}');
    } on ApiException catch (e) {
      if (mounted) setState(() => _banner = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Fills the whole form from a draft or a cloned ticket.
  void _apply(FormPrefill p, {required String label}) {
    final t = p.task;
    String s(Object? x) => x == null ? '' : '$x';
    final isDraft = p.draftId != null;

    setState(() {
      _inhouse = isDraft ? p.inHouse : _inhouse;
      for (final c in _text.values) {
        c.clear();
      }
      _choices.clear();
      _rows.clear();
      _files.clear();
      _kept.clear();
      _removed.clear();
      _errors = {};
      _banner = null;

      _draftId = p.draftId;
      _cloneSourceId = p.sourceId;
      _prefillLabel = label;

      _vertical = t['vertical'] as String?;
      _nature = t['task_nature'] as String?;
      _c('task_name').text = s(t['task_name']);
      _c('zoho_project_number').text = isDraft ? s(t['zoho_project_number']) : '';
      _partyType = (t['party_type'] as String?) == 'agency' ? 'agency' : 'client';
      _c('party_name').text = s(t['party_name']);
      _c('contact_person').text = s(t['contact_person']);
      _c('mobile_number').text = s(t['mobile_number']);
      _c('total_creatives').text = s(t['total_creatives']);
      _priority = def.priorities.contains(t['priority']) ? t['priority'] as String : 'medium';
      _due = null;
      if (isDraft && t['due_at'] != null) {
        final d = DateTime.tryParse(s(t['due_at']));
        if (d != null && d.isAfter(DateTime.now())) {
          _due = DateTime(d.year, d.month, d.day);
          _dueTime = TimeOfDay(hour: d.hour, minute: d.minute);
        }
      }

      final req = p.requirements;
      _creativeType = req['creative_type'] as String?;
      _rows.clear();
      for (final entry in req.entries) {
        final value = entry.value;
        if (value is List && (entry.key == 'dimension_rows' || entry.key == 'size_rows' || entry.key == 'media_size_rows')) {
          _rows[entry.key] = [
            for (final r in value)
              if (r is Map) {for (final e in r.entries) '${e.key}': s(e.value)},
          ];
        } else if (value is String || value is num) {
          final known = _dynamicFields.where((f) => f.key == entry.key).firstOrNull;
          if (known != null && (known.type == 'select' || known.type == 'vehicle_select')) {
            _choices[entry.key] = s(value);
          } else {
            _c(entry.key).text = s(value);
          }
        }
      }

      for (final e in p.attachments.entries) {
        _kept[e.key] = List.of(e.value);
      }

      _designerId = null;
      _availability = null;
    });

    final designer = t['designer_id'];
    if (designer is int && def.designers.any((d) => d.id == designer)) _pickDesigner(designer);
  }

  void _clearPrefill() => setState(() {
        _draftId = null;
        _cloneSourceId = null;
        _prefillLabel = null;
        _kept.clear();
        _removed.clear();
      });

  // ---------------------------------------------------------------------------------------------------------------------
  // Layout

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewPaddingOf(context).bottom;

    return Column(
      children: [
        Expanded(
          child: ListView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 16),
            children: [
              _kindToggle(),
              const SizedBox(height: 12),
              _helpers(),
              if (_cloneOpen) _clonePanel(),
              if (_prefillLabel != null) _prefillBanner(),
              if (_banner != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: const Color(0xFFFEF3F2), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFECDCA))),
                  child: Row(children: [
                    const Icon(Icons.error_outline_rounded, color: Color(0xFFD92D20), size: 20),
                    const SizedBox(width: 8),
                    Expanded(child: Text(_banner!, key: const Key('form-banner'), style: const TextStyle(color: Color(0xFFB42318), fontWeight: FontWeight.w600))),
                  ]),
                ),
              _detailsSection(),
              if (!_inhouse) _clientSection(),
              _scheduleSection(),
              if (_inhouse) _briefSection() else if (_nature != null) _requirementsSection(),
            ],
          ),
        ),
        _actionBar(bottom),
      ],
    );
  }

  Widget _kindToggle() => SegmentedButton<bool>(
        key: const Key('kind'),
        showSelectedIcon: false,
        style: SegmentedButton.styleFrom(
          backgroundColor: Colors.white,
          foregroundColor: const Color(0xFF55596A),
          selectedBackgroundColor: const Color(0xFFFFE8EA),
          selectedForegroundColor: adinnRed,
          side: const BorderSide(color: Color(0xFFE2E4EB)),
          textStyle: const TextStyle(fontWeight: FontWeight.w800),
          padding: const EdgeInsets.symmetric(vertical: 14),
        ),
        segments: const [
          ButtonSegment(value: false, label: Text('Client task'), icon: Icon(Icons.storefront_outlined, size: 18)),
          ButtonSegment(value: true, label: Text('In-house'), icon: Icon(Icons.apartment_rounded, size: 18)),
        ],
        selected: {_inhouse},
        onSelectionChanged: (s) => setState(() {
          _inhouse = s.first;
          _nature = null;
          _errors = {};
          _banner = null;
          _cloneHits = const [];
        }),
      );

  Widget _helpers() => Row(children: [
        Expanded(
          child: OutlinedButton.icon(
            key: const Key('clone-toggle'),
            style: _outlined(),
            icon: const Icon(Icons.copy_all_rounded, size: 18),
            label: const Text('Clone a ticket'),
            onPressed: () => setState(() => _cloneOpen = !_cloneOpen),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: OutlinedButton.icon(
            key: const Key('drafts-open'),
            style: _outlined(),
            icon: const Icon(Icons.drafts_outlined, size: 18),
            label: const Text('My drafts'),
            onPressed: _busy ? null : _openDrafts,
          ),
        ),
      ]);

  ButtonStyle _outlined() => OutlinedButton.styleFrom(
        foregroundColor: const Color(0xFF3B3F4D),
        backgroundColor: Colors.white,
        side: const BorderSide(color: Color(0xFFE2E4EB)),
        padding: const EdgeInsets.symmetric(vertical: 13),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5),
      );

  Widget _clonePanel() => Container(
        margin: const EdgeInsets.only(top: 12),
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFE2E4EB))),
        // Material (not a coloured DecoratedBox) underneath, so the list tiles can paint their ink.
        child: Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Clone an existing ticket', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          const SizedBox(height: 2),
          const Text('Search one of your earlier tickets to prefill this form. You can change anything before creating the task.', style: TextStyle(fontSize: 12, color: Color(0xFF8A8F9C))),
          const SizedBox(height: 10),
          TextField(
            key: const Key('clone-search'),
            controller: _cloneQuery,
            onChanged: _onCloneQuery,
            decoration: formDecoration('Task ID, Zoho no., name, client or mobile', prefix: const Icon(Icons.search_rounded), suffix: _cloneBusy ? const Padding(padding: EdgeInsets.all(14), child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))) : null),
          ),
          for (final h in _cloneHits)
            ListTile(
              key: Key('clone-hit-${h.id}'),
              contentPadding: EdgeInsets.zero,
              dense: true,
              title: Text(h.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text([h.taskId, if ((h.party ?? '').isNotEmpty) h.party!, if ((h.zoho ?? '').isNotEmpty) h.zoho!].where((x) => x.isNotEmpty).join('  ·  '), maxLines: 1, overflow: TextOverflow.ellipsis),
              trailing: const Icon(Icons.north_west_rounded, size: 18),
              onTap: () => _useClone(h),
            ),
        ]),
      ),
    ),
      );

  Widget _prefillBanner() => Container(
        margin: const EdgeInsets.only(top: 12),
        padding: const EdgeInsets.fromLTRB(14, 8, 6, 8),
        decoration: BoxDecoration(color: const Color(0xFFEFF8FF), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFB2DDFF))),
        child: Row(children: [
          const Icon(Icons.info_outline_rounded, size: 18, color: Color(0xFF175CD3)),
          const SizedBox(width: 8),
          Expanded(child: Text(_prefillLabel!, key: const Key('prefill-label'), style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF175CD3)))),
          TextButton(key: const Key('prefill-clear'), onPressed: _clearPrefill, child: Text(_draftId != null ? 'Detach' : 'Clear clone')),
        ]),
      );

  Widget _pad(Widget child) => Padding(padding: const EdgeInsets.only(bottom: 14), child: child);

  Widget _detailsSection() => FormSection(step: 1, title: 'Task details', subtitle: _inhouse ? 'An internal task for the design team' : 'What needs to be designed', children: [
        _text_('task_name', 'Task name', maxLength: 180),
        _dropdown('vertical', 'Vertical', {for (final o in def.verticals) o.key: o.label}, _vertical, (v) {
          setState(() {
            _vertical = v;
            _nature = null;
            _creativeType = null;
          });
        }),
        _dropdown('task_nature', 'Task nature', {for (final o in _natureOptions) o.key: o.label}, _nature, (v) {
          setState(() => _nature = v);
          _autofillInhouseClient();
        }),
        if (_inhouse && _nature == 'others') _text_('task_nature_other', 'Describe the nature'),
      ]);

  /// Digital / Inhouse + Inhouse nature: the client is Adinn itself, unless one was typed.
  void _autofillInhouseClient() {
    if (_inhouse || !_digital) return;
    final party = _c('party_name');
    if (_nature == 'inhouse' && (party.text.trim().isEmpty || party.text == def.inhouseClientName)) {
      party.text = def.inhouseClientName;
    } else if (_nature != 'inhouse' && party.text == def.inhouseClientName) {
      party.text = '';
    }
  }

  Widget _clientSection() => FormSection(step: 2, title: 'Client', subtitle: 'Who the work is for', children: [
        _dropdown('party_type', 'Client / Agency', const {'client': 'Client', 'agency': 'Agency'}, _partyType, (v) => setState(() => _partyType = v ?? 'client')),
        _clientName(),
        _text_('contact_person', 'Contact person', maxLength: 100),
        _text_('mobile_number', 'Mobile number', keyboard: TextInputType.phone, maxLength: 10, digitsOnly: true),
      ]);

  Widget _scheduleSection() => FormSection(step: _inhouse ? 2 : 3, title: _inhouse ? 'Assignment' : 'Schedule & assignment', subtitle: 'When it is due and who does it', children: [
        if (!_inhouse) ...[
          _dropdown('priority', 'Priority', {for (final p in def.priorities) p: p[0].toUpperCase() + p.substring(1)}, _priority, (v) => setState(() => _priority = v ?? 'medium')),
          _dueRow(),
          _text_('zoho_project_number', 'Zoho project number (optional)', maxLength: 60),
          if (_digital) _dropdown('creative_type', 'Creative type', {for (final t in (def.creativeTypes.isEmpty ? const ['Image Creative', 'Video Creative'] : def.creativeTypes)) t: t}, _creativeType, (v) => setState(() => _creativeType = v)),
        ],
        _designerField(),
        if (_showCreatives) _text_('total_creatives', 'Total number of creatives', keyboard: TextInputType.number, maxLength: 4, digitsOnly: true),
      ]);

  Widget _dueRow() => _pad(Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(
            flex: 3,
            child: InkWell(
              key: const Key('due-date'),
              borderRadius: BorderRadius.circular(13),
              onTap: _pickDue,
              child: InputDecorator(
                decoration: formDecoration('Due date', error: _errors['due_at'], suffix: const Icon(Icons.calendar_today_rounded, size: 18)),
                child: Text(_due == null ? 'Choose a date' : DateFormat('d MMM yyyy').format(_due!), style: TextStyle(color: _due == null ? const Color(0xFF9A9DA6) : null)),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            flex: 2,
            child: InkWell(
              key: const Key('due-time'),
              borderRadius: BorderRadius.circular(13),
              onTap: _pickTime,
              child: InputDecorator(decoration: formDecoration('Time', suffix: const Icon(Icons.schedule_rounded, size: 18)), child: Text(_dueTime.format(context))),
            ),
          ),
        ]),
      ]));

  Widget _designerField() {
    final chosen = def.designers.where((d) => d.id == _designerId).firstOrNull;

    return _pad(Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      DropdownButtonFormField<int>(
        key: const Key('f-designer_id'),
        initialValue: _designerId,
        isExpanded: true,
        decoration: formDecoration('Assign to designer *', error: _errors['designer_id']),
        selectedItemBuilder: (context) => [for (final d in def.designers) Align(alignment: Alignment.centerLeft, child: Text(d.name, overflow: TextOverflow.ellipsis))],
        items: [
          for (final d in def.designers)
            DropdownMenuItem(
              value: d.id,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(d.name, overflow: TextOverflow.ellipsis),
                  if (d.verticals.isNotEmpty) Text(d.verticals.map((v) => def.verticals.where((o) => o.key == v).map((o) => o.label).firstOrNull ?? v).join(', '), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, color: Color(0xFF8A8F9C))),
                ],
              ),
            ),
        ],
        onChanged: _pickDesigner,
      ),
      if (chosen != null && (chosen.verticals.isNotEmpty || chosen.skills.isNotEmpty))
        Padding(
          padding: const EdgeInsets.only(top: 10),
          child: Wrap(spacing: 6, runSpacing: 6, children: [
            for (final v in chosen.verticals) _chip(def.verticals.where((o) => o.key == v).map((o) => o.label).firstOrNull ?? v, const Color(0xFFFFE8EA), adinnRed),
            for (final sk in chosen.skills) _chip(sk, const Color(0xFFEEF2FF), const Color(0xFF3538CD)),
          ]),
        ),
      if (_availability != null) AvailabilityMeter(availability: _availability!),
    ]));
  }

  Widget _chip(String text, Color bg, Color fg) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: fg)),
      );

  Widget _briefSection() => FormSection(step: 3, title: 'Brief', subtitle: 'What the designer needs to know', children: [
        _text_('description', 'Description', lines: 5),
        _fileTile('attachments', 'Attachments', 'zipfiles', false),
      ]);

  Widget _requirementsSection() {
    final fields = _dynamicFields;
    final title = '${_labelOf(def.verticals.map((o) => o).toList(), _vertical)} · ${_labelOf(_natureOptions, _nature)}';

    final children = <Widget>[];
    final companions = {for (final x in fields) ?x.orUpload};
    for (final f in fields) {
      if (companions.contains(f.key)) continue;
      if (f.isSection) {
        children.add(SubHeading(f.label));
        continue;
      }
      children.add(_dynamicWidget(f));
    }

    return FormSection(step: 4, title: 'Requirements', subtitle: title, children: children);
  }

  Widget _dynamicWidget(FormFieldDef f) {
    final label = f.required ? '${f.label} *' : f.label;

    switch (f.type) {
      case 'dimensions':
      case 'sizes':
      case 'media_sizes':
        return _rowsEditor(f);
      case 'file':
      case 'files':
      case 'mediafiles':
      case 'zipfiles':
      case 'audio':
        return _fileTile(f.key, f.label, f.type, f.required);
      case 'select':
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _dropdown(f.key, label, {for (final o in f.options) o: o}, _choices[f.key], (v) => setState(() => _choices[f.key] = v)),
          if (_choices[f.key] == 'Other') _text_('${f.key}_other', 'Specify other *'),
        ]);
      case 'vehicle_select':
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _dropdown(f.key, label, {for (final o in f.options) o: o}, _choices[f.key], (v) => setState(() => _choices[f.key] = v)),
          if ((_choices[f.key] ?? '').isNotEmpty && _vehicleUrl(_choices[f.key]!) != null) VehiclePreview(name: _choices[f.key]!, url: _vehicleUrl(_choices[f.key]!)!),
        ]);
      case 'textarea':
        return _text_(f.key, label, lines: 3);
      case 'number':
        return _text_(f.key, label, keyboard: TextInputType.number, maxLength: 4, digitsOnly: true);
      case 'email':
        return _text_(f.key, label, keyboard: TextInputType.emailAddress);
      case 'url':
        return _text_(f.key, label, keyboard: TextInputType.url, hint: 'https://');
      case 'location':
        return _location(f, label);
      default:
        return f.key == 'creative_mobile_number' ? _text_(f.key, label, keyboard: TextInputType.phone, maxLength: 10, digitsOnly: true) : _text_(f.key, label);
    }
  }

  /// The server's picture path for a vehicle, on this server (the phone reaches the site by its own address).
  String? _vehicleUrl(String name) {
    final path = def.vehicleImages[name];
    if (path == null || path.isEmpty) return null;
    final i = path.indexOf('/images/');
    final rel = i >= 0 ? path.substring(i) : path;
    final origin = ref.read(serverUrlProvider).replaceAll(RegExp(r'/api/v1/?$'), '');
    return '$origin$rel';
  }

  Widget _actionBar(double bottom) => Container(
        padding: EdgeInsets.fromLTRB(14, 10, 14, 10 + bottom),
        decoration: const BoxDecoration(color: Colors.white, boxShadow: [BoxShadow(color: Color(0x14101828), blurRadius: 14, offset: Offset(0, -4))]),
        child: Row(children: [
          Expanded(
            child: OutlinedButton(
              key: const Key('save-draft'),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF3B3F4D),
                side: const BorderSide(color: Color(0xFFD5D8E0)),
                minimumSize: const Size(0, 52),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                textStyle: const TextStyle(fontWeight: FontWeight.w800),
              ),
              onPressed: _busy ? null : () => _submit(draft: true),
              child: Text(_draftId != null ? 'Update draft' : 'Save draft'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            flex: 2,
            child: FilledButton(
              key: const Key('create-task'),
              style: FilledButton.styleFrom(
                backgroundColor: adinnRed,
                minimumSize: const Size(0, 52),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                textStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
              ),
              onPressed: _busy ? null : () => _submit(draft: false),
              child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Create task'),
            ),
          ),
        ]),
      );

  // ---------------------------------------------------------------------------------------------------------------------
  // Field widgets

  Widget _text_(String key, String label, {int lines = 1, TextInputType? keyboard, int? maxLength, bool digitsOnly = false, String? hint}) => _pad(TextField(
        key: Key('f-$key'),
        controller: _c(key),
        minLines: lines,
        maxLines: lines > 1 ? lines + 2 : 1,
        maxLength: maxLength,
        keyboardType: keyboard,
        inputFormatters: digitsOnly ? [_DigitsOnly()] : null,
        textCapitalization: lines > 1 ? TextCapitalization.sentences : TextCapitalization.none,
        onChanged: (_) {
          if (_errors.containsKey(key)) setState(() => _errors.remove(key));
        },
        decoration: formDecoration(label, error: _errors[key], multiline: lines > 1, hint: hint).copyWith(counterText: ''),
      ));

  Widget _dropdown(String key, String label, Map<String, String> options, String? value, ValueChanged<String?> onChanged) => _pad(DropdownButtonFormField<String>(
        key: Key('f-$key-${options.keys.join(',')}'),
        initialValue: options.containsKey(value) ? value : null,
        isExpanded: true,
        borderRadius: BorderRadius.circular(14),
        decoration: formDecoration(label, error: _errors[key]),
        items: [for (final o in options.entries) DropdownMenuItem(value: o.key, child: Text(o.value, overflow: TextOverflow.ellipsis))],
        onChanged: (v) {
          onChanged(v);
          if (_errors.containsKey(key)) setState(() => _errors.remove(key));
        },
      ));

  Widget _clientName() => _pad(RawAutocomplete<ClientSuggestion>(
        textEditingController: _c('party_name'),
        focusNode: _f('party_name'),
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
        fieldViewBuilder: (context, controller, focus, onSubmit) => TextField(
          key: const Key('f-party_name'),
          controller: controller,
          focusNode: focus,
          onChanged: (_) {
            if (_errors.containsKey('party_name')) setState(() => _errors.remove('party_name'));
          },
          decoration: formDecoration('$_partyLabel name *', error: _errors['party_name'], suffix: const Icon(Icons.search_rounded, size: 20)),
        ),
        optionsViewBuilder: (context, onSelected, options) => _suggestions<ClientSuggestion>(options, onSelected, (c) => c.name, (c) => [if ((c.contact ?? '').isNotEmpty) c.contact!, if ((c.mobile ?? '').isNotEmpty) c.mobile!].join('  ·  ')),
      ));

  Widget _location(FormFieldDef f, String label) => _pad(RawAutocomplete<String>(
        textEditingController: _c(f.key),
        focusNode: _f(f.key),
        optionsBuilder: (value) {
          final q = value.text.trim().toLowerCase();
          return def.locations.where((l) => q.isEmpty || l.toLowerCase().contains(q));
        },
        fieldViewBuilder: (context, controller, focus, onSubmit) => TextField(
          key: Key('f-${f.key}'),
          controller: controller,
          focusNode: focus,
          decoration: formDecoration(label, error: _errors[f.key], hint: 'Search or enter location', suffix: const Icon(Icons.place_outlined, size: 20)),
        ),
        optionsViewBuilder: (context, onSelected, options) => _suggestions<String>(options, onSelected, (s) => s, (_) => ''),
      ));

  Widget _suggestions<T extends Object>(Iterable<T> options, AutocompleteOnSelected<T> onSelected, String Function(T) title, String Function(T) subtitle) => Align(
        alignment: Alignment.topLeft,
        child: Material(
          elevation: 6,
          borderRadius: BorderRadius.circular(14),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxHeight: 240, maxWidth: MediaQuery.sizeOf(context).width - 56),
            child: ListView(
              padding: EdgeInsets.zero,
              shrinkWrap: true,
              children: [
                for (final o in options)
                  ListTile(
                    dense: true,
                    title: Text(title(o), style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: subtitle(o).isEmpty ? null : Text(subtitle(o)),
                    onTap: () => onSelected(o),
                  ),
              ],
            ),
          ),
        ),
      );

  Widget _fileTile(String key, String label, String type, bool required) => FileFieldTile(
        fieldKey: key,
        label: label,
        type: type,
        required: required,
        files: _files.putIfAbsent(key, () => []),
        kept: _kept[key] ?? const [],
        error: _errors[key],
        onPick: () async {
          final single = type == 'file' || type == 'audio';
          final picked = await ref.read(filePickerProvider).pick(multiple: !single);
          if (picked.isEmpty || !mounted) return;
          final screened = screenPicked(type, picked);
          setState(() {
            final list = _files.putIfAbsent(key, () => []);
            if (single && screened.ok.isNotEmpty) list.clear();
            list.addAll(screened.ok);
            _errors.remove(key);
            // Shown under the field (a snackbar would sit on top of the Save / Create buttons).
            if (screened.rejected.isNotEmpty) {
              _errors[key] = type == 'audio' ? 'Only MP3 or WAV files are accepted: ${screened.rejected.join(', ')}' : 'Only images and videos are accepted: ${screened.rejected.join(', ')}';
            }
          });
        },
        onRemoveNew: (s) => setState(() => _files[key]?.remove(s)),
        onRemoveKept: (f) => setState(() {
          _kept[key]?.remove(f);
          // A draft's file is deleted on the server; a cloned file is simply not copied.
          if (_draftId != null) (_removed[key] ??= []).add(f.path);
        }),
      );

  Widget _rowsEditor(FormFieldDef f) {
    final key = f.rowsKey;
    final rows = _rows.putIfAbsent(key, () => [{for (final c in f.columns) c: ''}]);

    return RowsEditor(
      field: f,
      rows: rows,
      error: _errors[key],
      upload: f.orUpload == null ? null : _fileTile(f.orUpload!, 'Or upload a file', 'files', false),
      onChanged: () => setState(() => _errors.remove(key)),
      onAdd: () => setState(() => rows.add({for (final c in f.columns) c: ''})),
      onRemove: (i) => setState(() {
        if (rows.length == 1) {
          rows[0] = {for (final c in f.columns) c: ''};
        } else {
          rows.removeAt(i);
        }
      }),
    );
  }
}

class _DigitsOnly extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final digits = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');
    return digits == newValue.text ? newValue : TextEditingValue(text: digits, selection: TextSelection.collapsed(offset: digits.length));
  }
}

/// The saved drafts, newest first; picking one resumes it.
class _DraftsSheet extends ConsumerWidget {
  const _DraftsSheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final drafts = FutureBuilder<List<DraftSummary>>(
      future: ref.read(roleApiProvider).drafts(),
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) return const Padding(padding: EdgeInsets.all(40), child: Center(child: CircularProgressIndicator()));
        if (snap.hasError) return Padding(padding: const EdgeInsets.all(32), child: Text('${snap.error}', textAlign: TextAlign.center));
        final list = snap.data ?? const [];
        if (list.isEmpty) return const Padding(padding: EdgeInsets.all(40), child: Center(child: Text('No saved drafts yet.')));
        return ListView(
          shrinkWrap: true,
          children: [
            for (final d in list)
              ListTile(
                key: Key('draft-${d.id}'),
                leading: Icon(d.inHouse ? Icons.apartment_rounded : Icons.storefront_outlined, color: adinnRed),
                title: Text(d.name.isEmpty ? 'Untitled draft' : d.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text([if (d.inHouse) 'In-house', if ((d.party ?? '').isNotEmpty) d.party!, if (d.updatedAt != null) DateFormat('d MMM, h:mm a').format(d.updatedAt!)].join('  ·  ')),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => Navigator.pop(context, d),
              ),
          ],
        );
      },
    );

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.7),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Padding(padding: EdgeInsets.fromLTRB(20, 0, 20, 8), child: Text('Your drafts', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800))),
          Flexible(child: drafts),
        ]),
      ),
    );
  }
}
