import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../core/auth.dart';
import '../../core/models.dart';
import '../../core/uploads.dart';
import '../../core/theme.dart';
import '../uploads/upload_panel.dart';
import 'providers.dart';
import 'task_actions.dart';

class TaskDetailScreen extends ConsumerWidget {
  const TaskDetailScreen({super.key, required this.taskId});

  final int taskId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(taskDetailProvider(taskId));

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(detail.maybeWhen(data: (d) => (d['task']['task_id'] ?? 'Task') as String, orElse: () => 'Task')),
          bottom: const TabBar(tabs: [Tab(text: 'Overview'), Tab(text: 'Chat'), Tab(text: 'History')]),
        ),
        body: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text('$e', textAlign: TextAlign.center),
                const SizedBox(height: 12),
                OutlinedButton(onPressed: () => ref.invalidate(taskDetailProvider(taskId)), child: const Text('Try again')),
              ]),
            ),
          ),
          data: (d) => TabBarView(children: [
            _Overview(taskId: taskId, data: d),
            ChatTab(taskId: taskId),
            _History(data: d),
          ]),
        ),
      ),
    );
  }
}

class _Overview extends ConsumerStatefulWidget {
  const _Overview({required this.taskId, required this.data});

  final int taskId;
  final Map<String, dynamic> data;

  @override
  ConsumerState<_Overview> createState() => _OverviewState();
}

class _OverviewState extends ConsumerState<_Overview> {
  bool _moving = false;

  Future<void> _moveNext(String status) async {
    setState(() => _moving = true);
    try {
      await ref.read(roleApiProvider).moveStatus(widget.taskId, status);
      ref.invalidate(taskDetailProvider(widget.taskId));
      ref.invalidate(boardProvider);
    ref.invalidate(kanbanBoardProvider);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Moved to ${statusLabel(status)}')));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _moving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final task = widget.data['task'] as Map<String, dynamic>;
    final progress = (widget.data['progress'] as Map?)?.cast<String, dynamic>();
    final access = (widget.data['access'] as Map?)?.cast<String, dynamic>();
    final next = widget.data['next_status'] as String?;
    final role = ref.watch(authProvider).user?.role;
    final status = (task['status'] ?? '') as String;
    final due = task['due_at'] == null ? null : DateTime.tryParse(task['due_at'] as String)?.toLocal();
    final percentage = ((progress?['percentage'] ?? 0) as num).toDouble();

    final canMove = role == 'designer' && next != null && access?['read_only'] != true;

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(taskDetailProvider(widget.taskId));
        await ref.read(taskDetailProvider(widget.taskId).future);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text((task['display_task_name'] ?? task['task_name'] ?? '') as String, style: theme.textTheme.headlineSmall),
          const SizedBox(height: 8),
          Wrap(spacing: 8, children: [
            Chip(label: Text(statusLabel(status)), backgroundColor: statusColor(status).withValues(alpha: 0.12), side: BorderSide.none),
            Chip(label: Text('${task['priority']}'.toUpperCase()), backgroundColor: priorityColor('${task['priority']}').withValues(alpha: 0.12), side: BorderSide.none),
          ]),
          if (access?['read_only'] == true)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('This task is read-only for you.', style: TextStyle(color: Colors.black54)),
            ),
          const SizedBox(height: 16),
          if (progress != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Progress — ${progress['completed']} of ${progress['total']} creatives', style: theme.textTheme.titleSmall),
                  const SizedBox(height: 8),
                  LinearProgressIndicator(value: (percentage / 100).clamp(0, 1), minHeight: 8, borderRadius: BorderRadius.circular(8)),
                  const SizedBox(height: 4),
                  Text('${percentage.round()}% · ${progress['remaining']} remaining', style: theme.textTheme.bodySmall),
                ]),
              ),
            ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(children: [
                _Row('Vertical', '${task['vertical'] ?? '—'}'.replaceAll('_', ' ')),
                _Row('Nature', '${task['task_nature'] ?? '—'}'.replaceAll('_', ' ')),
                _Row('Client', '${task['party_name'] ?? '—'}'),
                _Row('Contact', '${task['contact_person'] ?? '—'}'),
                _Row('Mobile', '${task['mobile_number'] ?? '—'}', onTap: task['mobile_number'] == null ? null : () => launchUrl(Uri.parse('tel:${task['mobile_number']}'))),
                _Row('Due', due == null ? '—' : DateFormat('d MMM yyyy, h:mm a').format(due)),
                _Row('Creatives', '${task['total_creatives'] ?? '—'}'),
                if (task['bd'] != null) _Row('BD', '${(task['bd'] as Map)['name']}'),
                if (task['designer'] != null) _Row('Designer', '${(task['designer'] as Map)['name']}'),
              ]),
            ),
          ),
          TaskActions(taskId: widget.taskId, data: widget.data),
          if (canMove) ...[
            const SizedBox(height: 16),
            FilledButton.icon(
              key: const Key('move-next'),
              onPressed: _moving ? null : () => _moveNext(next),
              icon: _moving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.arrow_forward),
              label: Text('Move to ${statusLabel(next)}'),
            ),
          ],
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row(this.label, this.value, {this.onTap});

  final String label;
  final String value;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            SizedBox(width: 92, child: Text(label, style: const TextStyle(color: Colors.black54))),
            Expanded(child: Text(value, style: onTap == null ? null : TextStyle(color: Theme.of(context).colorScheme.primary))),
          ]),
        ),
      );
}

class _History extends StatelessWidget {
  const _History({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final history = ((data['history'] as List?) ?? []).cast<Map<String, dynamic>>().reversed.toList();
    if (history.isEmpty) return const Center(child: Text('No history yet.'));

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: history.length,
      separatorBuilder: (_, _) => const Divider(height: 20),
      itemBuilder: (_, i) {
        final h = history[i];
        final at = h['created_at'] == null ? null : DateTime.tryParse(h['created_at'] as String)?.toLocal();
        final to = h['to_status'] as String?;
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(to == null ? 'Updated' : 'Moved to ${statusLabel(to)}', style: const TextStyle(fontWeight: FontWeight.w600)),
          if ((h['note'] ?? '').toString().isNotEmpty) Text('${h['note']}'),
          if (at != null) Text(DateFormat('d MMM yyyy, h:mm a').format(at), style: const TextStyle(color: Colors.black54, fontSize: 12)),
        ]);
      },
    );
  }
}

/// Task chat. Polls every 8 s while open (the web does the same with its `chatState` check).
class ChatTab extends ConsumerStatefulWidget {
  const ChatTab({super.key, required this.taskId});

  final int taskId;

  @override
  ConsumerState<ChatTab> createState() => _ChatTabState();
}

class _ChatTabState extends ConsumerState<ChatTab> {
  final _text = TextEditingController();
  bool _sending = false;
  Timer? _poller;
  UploadsController? _uploads;

  /// Only designers and BD have the direct-upload chat endpoints.
  bool get _canAttach {
    final role = ref.read(authProvider).user?.role;
    return role == 'designer' || role == 'bd';
  }

  @override
  void initState() {
    super.initState();
    if (_canAttach) {
      _uploads = UploadsController(service: ref.read(uploadServiceProvider), taskId: widget.taskId, purpose: UploadPurpose.comment);
    }
    _poller = Timer.periodic(const Duration(seconds: 8), (_) {
      if (mounted) ref.invalidate(commentsProvider(widget.taskId));
    });
  }

  @override
  void dispose() {
    _poller?.cancel();
    _uploads?.dispose();
    _text.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final body = _text.text.trim();
    if (body.isEmpty || _sending) return;
    if (_uploads?.busy ?? false) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Wait for the attachments to finish uploading.')));
      return;
    }
    setState(() => _sending = true);
    try {
      await ref.read(roleApiProvider).sendComment(widget.taskId, body, uploadIds: _uploads?.idList);
      _text.clear();
      _uploads?.entries.clear();
      _uploads?.notice = null;
      ref.invalidate(commentsProvider(widget.taskId));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final me = ref.watch(authProvider).user?.id;
    final comments = ref.watch(commentsProvider(widget.taskId));

    return Column(
      children: [
        Expanded(
          child: comments.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('$e')),
            // Keep showing the last messages while a background refresh runs.
            skipLoadingOnRefresh: true,
            data: (messages) => messages.isEmpty
                ? const Center(child: Text('No messages yet.'))
                : ListView.builder(
                    reverse: true,
                    padding: const EdgeInsets.all(12),
                    itemCount: messages.length,
                    itemBuilder: (_, i) => _Bubble(message: messages[messages.length - 1 - i], mine: messages[messages.length - 1 - i].userId == me),
                  ),
          ),
        ),
        if (_uploads != null) Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: UploadPanel(controller: _uploads!, label: 'Attach files')),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 6, 12, 10),
            child: Row(children: [
              Expanded(
                child: TextField(
                  key: const Key('chat-input'),
                  controller: _text,
                  minLines: 1,
                  maxLines: 4,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(hintText: 'Message', isDense: true),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filled(
                key: const Key('chat-send'),
                onPressed: _sending ? null : _send,
                icon: _sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send),
              ),
            ]),
          ),
        ),
      ],
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.mine});

  final ChatMessage message;
  final bool mine;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final at = message.createdAt == null ? '' : DateFormat('d MMM, h:mm a').format(message.createdAt!);

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.all(10),
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.78),
        decoration: BoxDecoration(
          color: mine ? theme.colorScheme.primary.withValues(alpha: 0.12) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.black12),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (!mine) Text(message.userName, style: theme.textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700)),
          Text(message.text),
          for (final a in message.attachments)
            InkWell(
              onTap: a.url.isEmpty ? null : () => launchUrl(Uri.parse(a.url), mode: LaunchMode.externalApplication),
              child: Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.attach_file, size: 16),
                  Flexible(child: Text(a.name, style: TextStyle(color: theme.colorScheme.primary, decoration: TextDecoration.underline))),
                ]),
              ),
            ),
          const SizedBox(height: 2),
          Text(at, style: const TextStyle(fontSize: 10, color: Colors.black54)),
        ]),
      ),
    );
  }
}
