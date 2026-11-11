import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/theme.dart';
import '../create/create_task_widgets.dart' show formDecoration;
import 'kanban_models.dart';

const kVerticals = {
  'outdoor': 'Outdoor',
  'roadshow': 'RoadShow',
  'fixtures': 'Fixtures',
  'signage': 'Signage',
  'pop_offsets': 'POP and Offsets',
  'digital_marketing': 'Digital Marketing',
  'events_activations': 'Events and Activations',
  'media': 'Media',
  'digital_inhouse': 'Digital / Inhouse',
};

const kPriorities = {'overdue': 'Overdue', 'urgent': 'Urgent', 'high': 'High', 'medium': 'Medium', 'low': 'Low'};

const kPeriods = {'current_month': 'Current Month', 'last_month': 'Last Month', 'custom': 'Custom Period'};

/// The web toolbar's selects as a bottom sheet. Resolves to the new filters (or null if dismissed).
Future<BoardFilters?> showKanbanFilters(BuildContext context, {required BoardFilters current, required List<BdOption> bds}) {
  return showModalBottomSheet<BoardFilters>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
    builder: (_) => _FiltersSheet(current: current, bds: bds),
  );
}

class _FiltersSheet extends StatefulWidget {
  const _FiltersSheet({required this.current, required this.bds});

  final BoardFilters current;
  final List<BdOption> bds;

  @override
  State<_FiltersSheet> createState() => _FiltersSheetState();
}

class _FiltersSheetState extends State<_FiltersSheet> {
  late BoardFilters _f = widget.current;
  late final TextEditingController _project = TextEditingController(text: widget.current.projectNumber);

  @override
  void dispose() {
    _project.dispose();
    super.dispose();
  }

  Future<void> _pickDate({required bool from}) async {
    final now = DateTime.now();
    final initial = (from ? _f.dateFrom : _f.dateTo) ?? now;
    final picked = await showDatePicker(context: context, initialDate: initial, firstDate: DateTime(now.year - 3), lastDate: DateTime(now.year + 1));
    if (picked == null) return;
    setState(() => _f = from ? _f.copyWith(dateFrom: picked) : _f.copyWith(dateTo: picked));
  }

  DropdownMenuItem<String> _item(String value, String label, {Color? dot}) => DropdownMenuItem(
        value: value,
        child: Row(children: [
          if (dot != null) ...[Container(width: 9, height: 9, decoration: BoxDecoration(color: dot, shape: BoxShape.circle)), const SizedBox(width: 10)],
          Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
        ]),
      );

  static const _priorityColors = {'overdue': Color(0xFFD92D20), 'urgent': Color(0xFFF04438), 'high': Color(0xFFF79009), 'medium': Color(0xFF2E90FA), 'low': Color(0xFF12B76A)};

  /// How many filters differ from "everything this month".
  int get _active => [
        _f.vertical.isNotEmpty,
        _f.priority.isNotEmpty,
        _f.bdId != null,
        _project.text.trim().isNotEmpty,
        _f.period != 'current_month',
      ].where((x) => x).length;

  Widget _label(String text) => Padding(padding: const EdgeInsets.only(top: 4, bottom: 8), child: Text(text.toUpperCase(), style: const TextStyle(fontSize: 11, letterSpacing: 0.8, fontWeight: FontWeight.w800, color: Color(0xFF8A8F9C))));

  InputDecoration _deco(String label, IconData icon) => formDecoration(label, prefix: Icon(icon, size: 20, color: const Color(0xFF55596A)));

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('d MMM yyyy');

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, math.max(MediaQuery.viewInsetsOf(context).bottom, MediaQuery.viewPaddingOf(context).bottom) + 18),
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            Container(
              width: 40,
              height: 40,
              decoration: const BoxDecoration(color: Color(0xFFFFE8EA), shape: BoxShape.circle),
              child: const Icon(Icons.tune_rounded, color: adinnRed, size: 22),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Filters', style: TextStyle(fontSize: 21, fontWeight: FontWeight.w800, color: Color(0xFF16161A))),
                Text(_active == 0 ? 'Showing all tasks this month' : '$_active filter${_active == 1 ? '' : 's'} set', style: const TextStyle(fontSize: 12.5, color: Color(0xFF8A8F9C))),
              ]),
            ),
          ]),
          const SizedBox(height: 18),
          _label('Task'),
          DropdownButtonFormField<String>(
            key: const Key('f-vertical'),
            initialValue: _f.vertical,
            isExpanded: true,
            borderRadius: BorderRadius.circular(14),
            decoration: _deco('Vertical', Icons.category_outlined),
            items: [_item('', 'All Verticals'), for (final e in kVerticals.entries) _item(e.key, e.value)],
            onChanged: (v) => setState(() => _f = _f.copyWith(vertical: v ?? '')),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            key: const Key('f-priority'),
            initialValue: _f.priority,
            isExpanded: true,
            borderRadius: BorderRadius.circular(14),
            decoration: _deco('Priority', Icons.flag_outlined),
            items: [_item('', 'All Priorities'), for (final e in kPriorities.entries) _item(e.key, e.value, dot: _priorityColors[e.key])],
            onChanged: (v) => setState(() => _f = _f.copyWith(priority: v ?? '')),
          ),
          const SizedBox(height: 12),
          // A BD only ever sees their own tasks, so the board sends no BD list and the picker is left out.
          if (widget.bds.isNotEmpty) ...[
            DropdownButtonFormField<int?>(
              key: const Key('f-bd'),
              initialValue: widget.bds.any((b) => b.id == _f.bdId) ? _f.bdId : null,
              isExpanded: true,
              borderRadius: BorderRadius.circular(14),
              decoration: _deco('BD', Icons.person_outline_rounded),
              items: [const DropdownMenuItem<int?>(value: null, child: Text('All BD')), for (final b in widget.bds) DropdownMenuItem<int?>(value: b.id, child: Text(b.name, overflow: TextOverflow.ellipsis))],
              onChanged: (v) => setState(() => _f = _f.copyWith(bdId: v)),
            ),
            const SizedBox(height: 12),
          ],
          TextField(
            key: const Key('f-project'),
            controller: _project,
            decoration: _deco('Zoho Project Number', Icons.confirmation_number_outlined),
            onChanged: (v) => setState(() => _f = _f.copyWith(projectNumber: v)),
          ),
          const SizedBox(height: 14),
          _label('When'),
          DropdownButtonFormField<String>(
            key: const Key('f-period'),
            initialValue: _f.period,
            isExpanded: true,
            borderRadius: BorderRadius.circular(14),
            decoration: _deco('Period', Icons.calendar_month_outlined),
            items: [for (final e in kPeriods.entries) _item(e.key, e.value)],
            onChanged: (v) => setState(() => _f = _f.copyWith(period: v ?? 'current_month')),
          ),
          if (_f.period == 'custom') ...[
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: _dateButton(const Key('f-from'), 'From', _f.dateFrom, fmt, () => _pickDate(from: true))),
              const SizedBox(width: 10),
              Expanded(child: _dateButton(const Key('f-to'), 'To', _f.dateTo, fmt, () => _pickDate(from: false))),
            ]),
          ],
          const SizedBox(height: 22),
          Row(children: [
            Expanded(
              flex: 5,
              child: OutlinedButton.icon(
                key: const Key('clear-all'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: adinnRed,
                  side: const BorderSide(color: Color(0xFFFFC9CD)),
                  minimumSize: const Size(0, 52),
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                onPressed: () => Navigator.pop(context, BoardFilters(search: widget.current.search)),
                icon: const Icon(Icons.restart_alt_rounded, size: 20),
                label: const FittedBox(fit: BoxFit.scaleDown, child: Text('Clear Filters', maxLines: 1, style: TextStyle(fontWeight: FontWeight.w800))),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              flex: 6,
              child: FilledButton.icon(
                key: const Key('apply-filters'),
                style: FilledButton.styleFrom(backgroundColor: adinnRed, minimumSize: const Size(0, 52), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                onPressed: () => Navigator.pop(context, _f),
                icon: const Icon(Icons.check_rounded, size: 20),
                label: const Text('Apply', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
          ]),
        ]),
      ),
    );
  }

  Widget _dateButton(Key key, String hint, DateTime? value, DateFormat fmt, VoidCallback onTap) => InkWell(
        key: key,
        borderRadius: BorderRadius.circular(13),
        onTap: onTap,
        child: InputDecorator(
          decoration: formDecoration(hint, suffix: const Icon(Icons.event_rounded, size: 18)),
          child: Text(value == null ? 'Choose' : fmt.format(value), style: TextStyle(color: value == null ? const Color(0xFF9A9DA6) : null, fontWeight: FontWeight.w600)),
        ),
      );
}

/// "Filter: value" chips for whatever is non-default, as the web's "Applied Filters" row.
List<({String label, String value})> appliedChips(BoardFilters f, List<BdOption> bds, String periodLabel) => [
      if (f.search.trim().isNotEmpty) (label: 'Search', value: f.search.trim()),
      if (f.bdId != null) (label: 'BD', value: bds.where((b) => b.id == f.bdId).map((b) => b.name).firstOrNull ?? '—'),
      if (f.projectNumber.trim().isNotEmpty) (label: 'Zoho Project Number', value: f.projectNumber.trim()),
      if (f.vertical.isNotEmpty) (label: 'Vertical', value: kVerticals[f.vertical] ?? f.vertical),
      if (f.priority.isNotEmpty) (label: 'Priority', value: kPriorities[f.priority] ?? f.priority),
      if (f.period != 'current_month') (label: 'Period', value: periodLabel.isNotEmpty ? periodLabel : (kPeriods[f.period] ?? f.period)),
    ];
