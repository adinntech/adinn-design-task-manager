import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/models.dart';
import 'availability_meter.dart';

/// Input type of a [FormFieldSpec].
enum FormKind { text, number, choice }

/// Declarative field for [showFormSheet].
class FormFieldSpec {
  FormFieldSpec._(this.key, this.label, this.kind, {this.required = false, this.lines = 1, this.options = const {}, this.initial, this.availability});

  factory FormFieldSpec.text(String key, String label, {bool required = false, int lines = 1}) =>
      FormFieldSpec._(key, label, FormKind.text, required: required, lines: lines);

  factory FormFieldSpec.number(String key, String label, {bool required = false, int? initial}) =>
      FormFieldSpec._(key, label, FormKind.number, required: required, initial: initial?.toString());

  /// [options] maps the submitted value to its display label.
  ///
  /// With [availability], choosing a designer shows the workload meter under the field (informational only).
  factory FormFieldSpec.choice(String key, String label,
          {required Map<String, String> options, bool required = false, String? initial, Future<Availability> Function(int designerId)? availability}) =>
      FormFieldSpec._(key, label, FormKind.choice, required: required, options: options, initial: initial, availability: availability);

  final String key;
  final String label;
  final FormKind kind;
  final bool required;
  final int lines;
  final Map<String, String> options;
  final String? initial;
  final Future<Availability> Function(int designerId)? availability;
}

/// Bottom-sheet form. Resolves to the entered values, or null if dismissed.
Future<Map<String, String>?> showFormSheet(
  BuildContext context, {
  required String title,
  required String submitLabel,
  required List<FormFieldSpec> fields,
  bool destructive = false,
  Widget? header,
  String? subtitle,
  IconData? icon,
}) {
  return showModalBottomSheet<Map<String, String>>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
    builder: (_) => _FormSheet(title: title, submitLabel: submitLabel, fields: fields, destructive: destructive, header: header, subtitle: subtitle, icon: icon),
  );
}

class _FormSheet extends StatefulWidget {
  const _FormSheet({required this.title, required this.submitLabel, required this.fields, required this.destructive, this.header, this.subtitle, this.icon});

  final String title;
  final String submitLabel;
  final List<FormFieldSpec> fields;
  final bool destructive;
  final Widget? header;
  final String? subtitle;
  final IconData? icon;

  @override
  State<_FormSheet> createState() => _FormSheetState();
}

class _FormSheetState extends State<_FormSheet> {
  final _form = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _controllers = {
    for (final f in widget.fields) f.key: TextEditingController(text: f.initial ?? ''),
  };

  final Map<String, Availability> _availability = {};

  Future<void> _loadAvailability(FormFieldSpec f, String? value) async {
    final id = int.tryParse(value ?? '');
    setState(() => _availability.remove(f.key));
    if (id == null || f.availability == null) return;
    try {
      final a = await f.availability!(id);
      // Ignore a late answer for a designer that is no longer selected.
      if (mounted && _controllers[f.key]!.text == '$id') setState(() => _availability[f.key] = a);
    } catch (_) {} // the meter is optional
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _submit() {
    if (!_form.currentState!.validate()) return;
    Navigator.pop(context, {for (final e in _controllers.entries) e.key: e.value.text.trim()});
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = widget.destructive ? const Color(0xFFD92D20) : theme.colorScheme.primary;
    final icon = widget.icon ?? (widget.destructive ? Icons.warning_amber_rounded : Icons.edit_note_rounded);

    return Padding(
      // Clear of the phone's navigation bar, or of the keyboard when it is up, plus a little air under the button.
      padding: EdgeInsets.fromLTRB(18, 0, 18, math.max(MediaQuery.viewInsetsOf(context).bottom, MediaQuery.viewPaddingOf(context).bottom) + 22),
      child: SingleChildScrollView(
        child: Form(
          key: _form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(color: accent.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(14)),
                  child: Icon(icon, color: accent, size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(widget.title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -0.3)),
                    if (widget.subtitle != null && widget.subtitle!.isNotEmpty) Text(widget.subtitle!, style: const TextStyle(fontSize: 12.5, color: Color(0xFF667085), fontWeight: FontWeight.w600)),
                  ]),
                ),
              ]),
              if (widget.header != null) ...[const SizedBox(height: 12), widget.header!],
              const SizedBox(height: 18),
              for (final f in widget.fields) ...[
                _field(f),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 4),
              FilledButton(
                key: const Key('sheet-submit'),
                style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52), backgroundColor: widget.destructive ? theme.colorScheme.error : null),
                onPressed: _submit,
                child: Text(widget.submitLabel, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  IconData _iconFor(FormFieldSpec f) {
    final k = f.key;
    if (k.contains('designer')) return Icons.person_rounded;
    if (k.contains('count')) return Icons.layers_rounded;
    if (k.contains('reason') || k.contains('comment') || k.contains('details')) return Icons.notes_rounded;
    if (f.kind == FormKind.choice) return Icons.list_alt_rounded;
    return Icons.edit_rounded;
  }

  InputDecoration _decoration(FormFieldSpec f, {bool multiline = false}) => InputDecoration(
        labelText: f.label,
        alignLabelWithHint: multiline,
        filled: true,
        fillColor: const Color(0xFFF9FAFB),
        prefixIcon: multiline ? Padding(padding: const EdgeInsets.only(bottom: 44), child: Icon(_iconFor(f), size: 20)) : Icon(_iconFor(f), size: 20),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFD0D5DD))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFEAECF0))),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: Theme.of(context).colorScheme.primary, width: 1.6)),
        contentPadding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
      );

  Widget _field(FormFieldSpec f) {
    String? validate(String? v) => f.required && (v == null || v.trim().isEmpty) ? '${f.label} is required' : null;

    switch (f.kind) {
      case FormKind.choice:
        final dropdown = DropdownButtonFormField<String>(
          key: Key('field-${f.key}'),
          initialValue: f.options.containsKey(_controllers[f.key]!.text) ? _controllers[f.key]!.text : null,
          decoration: _decoration(f),
          isExpanded: true,
          items: [for (final o in f.options.entries) DropdownMenuItem(value: o.key, child: Text(o.value, overflow: TextOverflow.ellipsis))],
          onChanged: (v) {
            _controllers[f.key]!.text = v ?? '';
            if (f.availability != null) _loadAvailability(f, v);
          },
          validator: (v) => validate(v),
        );
        final meter = _availability[f.key];
        if (meter == null) return dropdown;
        return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [dropdown, AvailabilityMeter(availability: meter)]);
      case FormKind.number:
        return TextFormField(
          key: Key('field-${f.key}'),
          controller: _controllers[f.key],
          keyboardType: TextInputType.number,
          decoration: _decoration(f),
          validator: (v) {
            final base = validate(v);
            if (base != null) return base;
            if (v != null && v.trim().isNotEmpty && (int.tryParse(v.trim()) ?? 0) < 1) return 'Enter a whole number of 1 or more';
            return null;
          },
        );
      case FormKind.text:
        return TextFormField(
          key: Key('field-${f.key}'),
          controller: _controllers[f.key],
          minLines: f.lines,
          maxLines: f.lines > 1 ? f.lines + 2 : 1,
          textCapitalization: TextCapitalization.sentences,
          decoration: _decoration(f, multiline: f.lines > 1),
          validator: validate,
        );
    }
  }
}
