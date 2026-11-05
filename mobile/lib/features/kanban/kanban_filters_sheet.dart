import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

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

  DropdownMenuItem<String> _item(String value, String label) => DropdownMenuItem(value: value, child: Text(label));

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('d MMM yyyy');

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('Filters', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            key: const Key('f-vertical'),
            initialValue: _f.vertical,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Vertical'),
            items: [_item('', 'All Verticals'), for (final e in kVerticals.entries) _item(e.key, e.value)],
            onChanged: (v) => setState(() => _f = _f.copyWith(vertical: v ?? '')),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            key: const Key('f-priority'),
            initialValue: _f.priority,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Priority'),
            items: [_item('', 'All Priorities'), for (final e in kPriorities.entries) _item(e.key, e.value)],
            onChanged: (v) => setState(() => _f = _f.copyWith(priority: v ?? '')),
          ),
          const SizedBox(height: 12),
          // A BD only ever sees their own tasks, so the board sends no BD list and the picker is left out.
          if (widget.bds.isNotEmpty) ...[
            DropdownButtonFormField<int?>(
              key: const Key('f-bd'),
              initialValue: widget.bds.any((b) => b.id == _f.bdId) ? _f.bdId : null,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'BD'),
              items: [const DropdownMenuItem<int?>(value: null, child: Text('All BD')), for (final b in widget.bds) DropdownMenuItem<int?>(value: b.id, child: Text(b.name))],
              onChanged: (v) => setState(() => _f = _f.copyWith(bdId: v)),
            ),
            const SizedBox(height: 12),
          ],
          TextField(
            key: const Key('f-project'),
            controller: _project,
            decoration: const InputDecoration(labelText: 'Zoho Project Number'),
            onChanged: (v) => _f = _f.copyWith(projectNumber: v),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            key: const Key('f-period'),
            initialValue: _f.period,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Period'),
            items: [for (final e in kPeriods.entries) _item(e.key, e.value)],
            onChanged: (v) => setState(() => _f = _f.copyWith(period: v ?? 'current_month')),
          ),
          if (_f.period == 'custom') ...[
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: OutlinedButton(key: const Key('f-from'), onPressed: () => _pickDate(from: true), child: Text(_f.dateFrom == null ? 'From' : fmt.format(_f.dateFrom!))),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton(key: const Key('f-to'), onPressed: () => _pickDate(from: false), child: Text(_f.dateTo == null ? 'To' : fmt.format(_f.dateTo!))),
              ),
            ]),
          ],
          const SizedBox(height: 18),
          Row(children: [
            Expanded(
              child: OutlinedButton(
                key: const Key('clear-all'),
                onPressed: () => Navigator.pop(context, BoardFilters(search: widget.current.search)),
                child: const Text('Clear Filters'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(flex: 2, child: FilledButton(key: const Key('apply-filters'), onPressed: () => Navigator.pop(context, _f), child: const Text('Apply'))),
          ]),
        ]),
      ),
    );
  }
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
