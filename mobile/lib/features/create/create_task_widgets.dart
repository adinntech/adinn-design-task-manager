import 'package:flutter/material.dart';

import '../../core/models.dart';
import '../../core/theme.dart';
import '../../core/uploads.dart';

/// The soft, filled input look shared by the whole form (same family as the sign-in screen).
InputDecoration formDecoration(String label, {String? error, Widget? suffix, Widget? prefix, String? hint, bool multiline = false, bool dense = false}) => InputDecoration(
      labelText: label,
      hintText: hint,
      errorText: error,
      errorMaxLines: 3,
      suffixIcon: suffix,
      prefixIcon: prefix,
      isDense: dense,
      alignLabelWithHint: multiline,
      filled: true,
      fillColor: const Color(0xFFF6F7FA),
      labelStyle: const TextStyle(fontSize: 14, color: Color(0xFF7A7F8C), fontWeight: FontWeight.w600),
      floatingLabelStyle: const TextStyle(fontSize: 13, color: Color(0xFF55596A), fontWeight: FontWeight.w700),
      contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: dense ? 12 : 15),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: BorderSide.none),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: BorderSide.none),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: adinnRed, width: 1.5)),
      errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFFD92D20), width: 1.2)),
      focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFFD92D20), width: 1.5)),
    );

/// A white card with a numbered header: one block of the form.
class FormSection extends StatelessWidget {
  const FormSection({super.key, required this.step, required this.title, this.subtitle, required this.children, this.trailing});

  final int step;
  final String title;
  final String? subtitle;
  final List<Widget> children;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 6),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20), boxShadow: const [BoxShadow(color: Color(0x0F101828), blurRadius: 16, offset: Offset(0, 6))]),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: Row(
              children: [
                Container(
                  width: 28,
                  height: 28,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(color: Color(0xFFFFE8EA), shape: BoxShape.circle),
                  child: Text('$step', style: const TextStyle(color: adinnRed, fontWeight: FontWeight.w800, fontSize: 13)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF16161A))),
                      if (subtitle != null) Text(subtitle!, style: const TextStyle(fontSize: 12, color: Color(0xFF8A8F9C))),
                    ],
                  ),
                ),
                ?trailing,
              ],
            ),
          ),
          ...children,
        ],
      ),
    );
  }
}

/// A heading inside the requirements block (the web form's "Description", "Company Details"... groups).
class SubHeading extends StatelessWidget {
  const SubHeading(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 8, bottom: 10),
        child: Row(children: [
          Container(width: 3, height: 14, decoration: BoxDecoration(color: adinnRed, borderRadius: BorderRadius.circular(2))),
          const SizedBox(width: 8),
          Text(text.toUpperCase(), style: const TextStyle(fontSize: 11.5, letterSpacing: 0.8, fontWeight: FontWeight.w800, color: Color(0xFF55596A))),
        ]),
      );
}

const _imageExt = {'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif', 'svg', 'tif', 'tiff'};
const _videoExt = {'mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', 'wmv', '3gp'};

String _ext(String name) => name.contains('.') ? name.split('.').last.toLowerCase() : '';

/// Applies the web form's file rules to what was picked: Audio Reference takes only MP3 / WAV, Creative Content takes
/// images and videos. Returns the files that pass and the names that were turned away.
({List<UploadSource> ok, List<String> rejected}) screenPicked(String type, List<UploadSource> picked) {
  bool allowed(UploadSource f) => switch (type) {
        'audio' => const {'mp3', 'wav'}.contains(_ext(f.name)),
        'mediafiles' => _imageExt.contains(_ext(f.name)) || _videoExt.contains(_ext(f.name)),
        _ => true,
      };
  return (ok: [for (final f in picked) if (allowed(f)) f], rejected: [for (final f in picked) if (!allowed(f)) f.name]);
}

String fileHint(String type) => switch (type) {
      'audio' => 'MP3 or WAV only',
      'mediafiles' => 'Images or videos',
      'zipfiles' => 'Any files; anything over 500 MB must be a ZIP',
      'file' => 'One file',
      _ => 'One or more files',
    };

/// An upload field: the button, the files already on the server (from a draft or a cloned ticket) with a remove cross,
/// and the newly picked ones.
class FileFieldTile extends StatelessWidget {
  const FileFieldTile({super.key, required this.fieldKey, required this.label, required this.type, required this.required, required this.files, required this.kept, required this.onPick, required this.onRemoveNew, required this.onRemoveKept, this.error});

  final String fieldKey;
  final String label;
  final String type;
  final bool required;
  final List<UploadSource> files;
  final List<StoredFile> kept;
  final VoidCallback onPick;
  final ValueChanged<UploadSource> onRemoveNew;
  final ValueChanged<StoredFile> onRemoveKept;
  final String? error;

  @override
  Widget build(BuildContext context) {
    final hasError = error != null;
    final icon = switch (type) {
      'audio' => Icons.mic_none_rounded,
      'mediafiles' => Icons.perm_media_outlined,
      'zipfiles' => Icons.folder_zip_outlined,
      _ => Icons.attach_file_rounded,
    };

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(14, 12, 8, 8),
      decoration: BoxDecoration(
        color: const Color(0xFFFAFAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: hasError ? const Color(0xFFD92D20) : const Color(0xFFE6E8EE), width: hasError ? 1.3 : 1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 20, color: adinnRed),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('$label${required ? ' *' : ''}', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF16161A))),
                    Text(fileHint(type), style: const TextStyle(fontSize: 11.5, color: Color(0xFF8A8F9C))),
                  ],
                ),
              ),
              TextButton.icon(
                key: Key('pick-$fieldKey'),
                style: TextButton.styleFrom(foregroundColor: adinnRed, visualDensity: VisualDensity.compact),
                icon: const Icon(Icons.add_rounded, size: 18),
                label: Text(files.isEmpty && kept.isEmpty ? 'Add' : 'Add more'),
                onPressed: onPick,
              ),
            ],
          ),
          for (final f in kept)
            _FileRow(name: f.name, note: 'Saved', icon: Icons.cloud_done_outlined, onRemove: () => onRemoveKept(f), removeKey: Key('remove-kept-$fieldKey-${f.name}')),
          for (final s in files) _FileRow(name: s.name, icon: Icons.insert_drive_file_outlined, onRemove: () => onRemoveNew(s), removeKey: Key('remove-$fieldKey-${s.name}')),
          if (hasError) Padding(padding: const EdgeInsets.only(top: 4, bottom: 4), child: Text(error!, style: const TextStyle(color: Color(0xFFD92D20), fontSize: 12.5))),
        ],
      ),
    );
  }
}

class _FileRow extends StatelessWidget {
  const _FileRow({required this.name, required this.icon, required this.onRemove, required this.removeKey, this.note});

  final String name;
  final String? note;
  final IconData icon;
  final VoidCallback onRemove;
  final Key removeKey;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 6),
        child: Container(
          padding: const EdgeInsets.only(left: 10),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFEDEFF4))),
          child: Row(
            children: [
              Icon(icon, size: 18, color: const Color(0xFF7A7F8C)),
              const SizedBox(width: 8),
              Expanded(child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13))),
              if (note != null) Text(note!, style: const TextStyle(fontSize: 11, color: Color(0xFF1FA463), fontWeight: FontWeight.w700)),
              IconButton(key: removeKey, tooltip: 'Remove', visualDensity: VisualDensity.compact, icon: const Icon(Icons.close_rounded, size: 18), onPressed: onRemove),
            ],
          ),
        ),
      );
}

/// The Road Show vehicle picture under the vehicle dropdown.
class VehiclePreview extends StatelessWidget {
  const VehiclePreview({super.key, required this.name, required this.url});

  final String name;
  final String url;

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(top: 10, bottom: 14),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: const Color(0xFFFFF6F6), borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFFFFD9DC))),
        child: Column(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Image.network(
                url,
                key: const Key('vehicle-preview'),
                height: 150,
                width: double.infinity,
                fit: BoxFit.contain,
                loadingBuilder: (context, child, progress) => progress == null ? child : const SizedBox(height: 150, child: Center(child: CircularProgressIndicator(strokeWidth: 2))),
                errorBuilder: (_, _, _) => const SizedBox(height: 60, child: Center(child: Icon(Icons.local_shipping_outlined, color: Color(0xFFB0B4C0), size: 32))),
              ),
            ),
            const SizedBox(height: 6),
            Text(name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          ],
        ),
      );
}

/// The editable rows of a size table: Name / Width / Height with the area worked out as you type (Board and Print sizes), or
/// Name / Width / Height / Ratio (Media). Same completeness rules as the web form.
class RowsEditor extends StatelessWidget {
  const RowsEditor({super.key, required this.field, required this.rows, required this.onChanged, required this.onAdd, required this.onRemove, this.upload, this.error});

  final FormFieldDef field;
  final List<Map<String, String>> rows;
  final VoidCallback onChanged;
  final VoidCallback onAdd;
  final ValueChanged<int> onRemove;
  final Widget? upload;
  final String? error;

  static String area(Map<String, String> row) {
    final w = double.tryParse(row['width'] ?? '') ?? 0;
    final h = double.tryParse(row['height'] ?? '') ?? 0;
    return w > 0 && h > 0 ? (w * h).toStringAsFixed(2) : '';
  }

  String _label(String c) => c == 'name' ? 'Name' : c[0].toUpperCase() + c.substring(1);

  @override
  Widget build(BuildContext context) {
    final key = field.rowsKey;
    final withArea = field.type != 'media_sizes';

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFAFAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: error != null ? const Color(0xFFD92D20) : const Color(0xFFE6E8EE), width: error != null ? 1.3 : 1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.straighten_rounded, size: 20, color: adinnRed),
              const SizedBox(width: 10),
              Expanded(child: Text('${field.label}${field.required ? ' *' : ''}', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700))),
              TextButton.icon(key: Key('add-row-$key'), style: TextButton.styleFrom(foregroundColor: adinnRed, visualDensity: VisualDensity.compact), icon: const Icon(Icons.add_rounded, size: 18), label: const Text('Add row'), onPressed: onAdd),
            ],
          ),
          for (var i = 0; i < rows.length; i++)
            Container(
              key: ObjectKey(rows[i]), // a removed row must not hand its typed text to the one below it
              margin: const EdgeInsets.only(top: 10),
              padding: const EdgeInsets.fromLTRB(10, 10, 4, 10),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFEDEFF4))),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(child: _cell(key, i, 'name', rows[i], text: true)),
                      IconButton(tooltip: 'Remove row', icon: const Icon(Icons.close_rounded, size: 20), onPressed: rows.length == 1 && rows[i].values.every((v) => v.isEmpty) ? null : () => onRemove(i)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      for (final c in field.columns.where((c) => c != 'name')) ...[
                        Expanded(child: _cell(key, i, c, rows[i], text: c == 'ratio')),
                        const SizedBox(width: 8),
                      ],
                      if (withArea)
                        Expanded(
                          child: InputDecorator(
                            decoration: formDecoration('Area', dense: true),
                            child: Text(area(rows[i]).isEmpty ? '—' : area(rows[i]), key: Key('area-$key-$i'), style: const TextStyle(fontWeight: FontWeight.w700)),
                          ),
                        ),
                      const SizedBox(width: 4),
                    ],
                  ),
                ],
              ),
            ),
          if (upload != null) Padding(padding: const EdgeInsets.only(top: 12), child: upload),
          if (error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(error!, style: const TextStyle(color: Color(0xFFD92D20), fontSize: 12.5))),
        ],
      ),
    );
  }

  Widget _cell(String key, int i, String c, Map<String, String> row, {required bool text}) => TextFormField(
        key: Key('row-$key-$i-$c'),
        initialValue: row[c],
        keyboardType: text ? TextInputType.text : const TextInputType.numberWithOptions(decimal: true),
        decoration: formDecoration(_label(c), dense: true, hint: c == 'ratio' ? 'e.g. 16:9' : null),
        onChanged: (v) {
          row[c] = v;
          onChanged();
        },
      );
}
