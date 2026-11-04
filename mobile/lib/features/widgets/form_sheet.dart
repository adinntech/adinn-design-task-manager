import 'package:flutter/material.dart';

/// Input type of a [FormFieldSpec].
enum FormKind { text, number, choice }

/// Declarative field for [showFormSheet].
class FormFieldSpec {
  FormFieldSpec._(this.key, this.label, this.kind, {this.required = false, this.lines = 1, this.options = const {}, this.initial});

  factory FormFieldSpec.text(String key, String label, {bool required = false, int lines = 1}) =>
      FormFieldSpec._(key, label, FormKind.text, required: required, lines: lines);

  factory FormFieldSpec.number(String key, String label, {bool required = false, int? initial}) =>
      FormFieldSpec._(key, label, FormKind.number, required: required, initial: initial?.toString());

  /// [options] maps the submitted value to its display label.
  factory FormFieldSpec.choice(String key, String label, {required Map<String, String> options, bool required = false, String? initial}) =>
      FormFieldSpec._(key, label, FormKind.choice, required: required, options: options, initial: initial);

  final String key;
  final String label;
  final FormKind kind;
  final bool required;
  final int lines;
  final Map<String, String> options;
  final String? initial;
}

/// Bottom-sheet form. Resolves to the entered values, or null if dismissed.
Future<Map<String, String>?> showFormSheet(
  BuildContext context, {
  required String title,
  required String submitLabel,
  required List<FormFieldSpec> fields,
  bool destructive = false,
  Widget? header,
}) {
  return showModalBottomSheet<Map<String, String>>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _FormSheet(title: title, submitLabel: submitLabel, fields: fields, destructive: destructive, header: header),
  );
}

class _FormSheet extends StatefulWidget {
  const _FormSheet({required this.title, required this.submitLabel, required this.fields, required this.destructive, this.header});

  final String title;
  final String submitLabel;
  final List<FormFieldSpec> fields;
  final bool destructive;
  final Widget? header;

  @override
  State<_FormSheet> createState() => _FormSheetState();
}

class _FormSheetState extends State<_FormSheet> {
  final _form = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _controllers = {
    for (final f in widget.fields) f.key: TextEditingController(text: f.initial ?? ''),
  };

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

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
      child: SingleChildScrollView(
        child: Form(
          key: _form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(widget.title, style: theme.textTheme.titleLarge),
              if (widget.header != null) ...[const SizedBox(height: 12), widget.header!],
              const SizedBox(height: 16),
              for (final f in widget.fields) ...[
                _field(f),
                const SizedBox(height: 12),
              ],
              FilledButton(
                key: const Key('sheet-submit'),
                style: widget.destructive ? FilledButton.styleFrom(backgroundColor: theme.colorScheme.error) : null,
                onPressed: _submit,
                child: Text(widget.submitLabel),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _field(FormFieldSpec f) {
    String? validate(String? v) => f.required && (v == null || v.trim().isEmpty) ? '${f.label} is required' : null;

    switch (f.kind) {
      case FormKind.choice:
        return DropdownButtonFormField<String>(
          key: Key('field-${f.key}'),
          initialValue: f.options.containsKey(_controllers[f.key]!.text) ? _controllers[f.key]!.text : null,
          decoration: InputDecoration(labelText: f.label),
          isExpanded: true,
          items: [for (final o in f.options.entries) DropdownMenuItem(value: o.key, child: Text(o.value, overflow: TextOverflow.ellipsis))],
          onChanged: (v) => _controllers[f.key]!.text = v ?? '',
          validator: (v) => validate(v),
        );
      case FormKind.number:
        return TextFormField(
          key: Key('field-${f.key}'),
          controller: _controllers[f.key],
          keyboardType: TextInputType.number,
          decoration: InputDecoration(labelText: f.label),
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
          decoration: InputDecoration(labelText: f.label, alignLabelWithHint: f.lines > 1),
          validator: validate,
        );
    }
  }
}
