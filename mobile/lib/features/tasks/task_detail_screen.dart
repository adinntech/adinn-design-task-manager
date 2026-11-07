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
    final ticket = detail.maybeWhen(data: (d) => (d['task']['task_id'] ?? 'Task') as String, orElse: () => 'Task');

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(ticket),
          actions: [
            // The conversation is one tap from the ticket's name rather than a tab of its own.
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: IconButton(
                key: const Key('open-chat'),
                tooltip: 'Chat',
                style: IconButton.styleFrom(backgroundColor: Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)),
                icon: Icon(Icons.chat_bubble_rounded, size: 20, color: Theme.of(context).colorScheme.primary),
                onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ChatScreen(taskId: taskId, ticket: ticket))),
              ),
            ),
          ],
          bottom: const TabBar(tabs: [Tab(text: 'Overview'), Tab(text: 'History')]),
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
    final task = widget.data['task'] as Map<String, dynamic>;
    final progress = (widget.data['progress'] as Map?)?.cast<String, dynamic>();
    final access = (widget.data['access'] as Map?)?.cast<String, dynamic>();
    final next = widget.data['next_status'] as String?;
    final role = ref.watch(authProvider).user?.role;
    final status = (task['status'] ?? '') as String;
    final due = task['due_at'] == null ? null : DateTime.tryParse(task['due_at'] as String)?.toLocal();
    final percentage = ((progress?['percentage'] ?? 0) as num).toDouble();

    final canMove = role == 'designer' && next != null && access?['read_only'] != true;

    // Everything is laid out to fit one screen: the details sit in two columns, and the actions are pushed to the bottom.
    // It only scrolls if a very small screen (or large text) really cannot hold it.
    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(taskDetailProvider(widget.taskId));
        await ref.read(taskDetailProvider(widget.taskId).future);
      },
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverFillRemaining(
            hasScrollBody: false,
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text((task['display_task_name'] ?? task['task_name'] ?? '') as String, style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800, letterSpacing: -0.4, height: 1.15), maxLines: 2, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 8),
                  Row(children: [
                    _Tag(statusLabel(status), statusColor(status)),
                    const SizedBox(width: 6),
                    _Tag('${task['priority']}'.toUpperCase(), priorityColor('${task['priority']}')),
                    if (access?['read_only'] == true) ...[
                      const SizedBox(width: 8),
                      const Flexible(child: Text('Read-only for you', style: TextStyle(color: Colors.black54, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis)),
                    ],
                  ]),
                  const SizedBox(height: 12),
                  if (progress != null) _ProgressCard(progress: progress, percentage: percentage),
                  if (progress != null) const SizedBox(height: 10),
                  _Details(items: [
                    ('Vertical', '${task['vertical'] ?? '—'}'.replaceAll('_', ' '), null),
                    ('Nature', '${task['task_nature'] ?? '—'}'.replaceAll('_', ' '), null),
                    ('Client', '${task['party_name'] ?? '—'}', null),
                    ('Contact', '${task['contact_person'] ?? '—'}', null),
                    ('Mobile', '${task['mobile_number'] ?? '—'}', task['mobile_number'] == null ? null : () => launchUrl(Uri.parse('tel:${task['mobile_number']}'))),
                    ('Creatives', '${task['total_creatives'] ?? '—'}', null),
                    ('Due', due == null ? '—' : DateFormat('d MMM yyyy, h:mm a').format(due), null),
                    if (task['bd'] != null) ('BD', '${(task['bd'] as Map)['name']}', null),
                    if (task['designer'] != null) ('Designer', '${(task['designer'] as Map)['name']}', null),
                  ]),
                  const Spacer(),
                  const SizedBox(height: 10),
                  TaskActions(taskId: widget.taskId, data: widget.data),
                  if (canMove) ...[
                    const SizedBox(height: 8),
                    FilledButton.icon(
                      key: const Key('move-next'),
                      onPressed: _moving ? null : () => _moveNext(next),
                      icon: _moving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.arrow_forward),
                      label: Text('Move to ${statusLabel(next)}'),
                    ),
                  ],
                ]),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Tag extends StatelessWidget {
  const _Tag(this.text, this.color);

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 11.5, fontWeight: FontWeight.w800, letterSpacing: 0.2)),
      );
}

class _ProgressCard extends StatelessWidget {
  const _ProgressCard({required this.progress, required this.percentage});

  final Map<String, dynamic> progress;
  final double percentage;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFEAECF0))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('Progress — ${progress['completed']} of ${progress['total']} creatives', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800))),
          Text('${percentage.round()}%', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: primary)),
        ]),
        const SizedBox(height: 8),
        LinearProgressIndicator(value: (percentage / 100).clamp(0, 1), minHeight: 8, borderRadius: BorderRadius.circular(8), backgroundColor: const Color(0xFFFEE4E2)),
        const SizedBox(height: 5),
        Text('${progress['remaining']} remaining', style: const TextStyle(fontSize: 11.5, color: Color(0xFF667085))),
      ]),
    );
  }
}

/// The ticket's facts as a two-column grid: a small label over its value.
class _Details extends StatelessWidget {
  const _Details({required this.items});

  final List<(String, String, VoidCallback?)> items;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    // two to a row; the date, the longest value, gets a row to itself
    final normal = [for (final it in items) if (it.$1 != 'Due') it];
    final rows = <List<(String, String, VoidCallback?)>>[
      for (var i = 0; i < normal.length; i += 2) normal.sublist(i, i + 2 > normal.length ? normal.length : i + 2),
      for (final it in items) if (it.$1 == 'Due') [it],
    ];

    Widget cell((String, String, VoidCallback?) it) {
      final (label, value, onTap) = it;
      return InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label.toUpperCase(), style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.6, color: Color(0xFF98A2B3))),
            const SizedBox(height: 2),
            Text(value, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: onTap == null ? const Color(0xFF101828) : primary)),
          ]),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 4),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFEAECF0))),
      // Plain rows rather than a LayoutBuilder: this sits in a SliverFillRemaining, which needs intrinsic sizes.
      child: Column(children: [
        for (var i = 0; i < rows.length; i++)
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            for (var j = 0; j < rows[i].length; j++) ...[
              if (j > 0) const SizedBox(width: 12),
              Expanded(child: cell(rows[i][j])),
            ],
            if (rows[i].length == 1 && rows[i].first.$1 != 'Due') const Expanded(child: SizedBox.shrink()),
          ]),
      ]),
    );
  }
}

/// The ticket's journey as a vertical timeline: an icon per step joined by a line, the oldest first, ending on the step
/// the ticket is at now.
class _History extends StatelessWidget {
  const _History({required this.data});

  final Map<String, dynamic> data;

  static IconData _icon(String? status) => switch (status) {
        'assigned_tasks' => Icons.send_rounded,
        'completed' => Icons.check_circle_outline_rounded,
        'rework' => Icons.replay_rounded,
        'in_progress' => Icons.autorenew_rounded,
        'waiting_confirmation' => Icons.hourglass_empty_rounded,
        'prepare_printing_file' => Icons.print_outlined,
        'need_clarification' => Icons.help_outline_rounded,
        'swap_tasks' => Icons.swap_horiz_rounded,
        _ => Icons.arrow_forward_rounded,
      };

  @override
  Widget build(BuildContext context) {
    final history = ((data['history'] as List?) ?? []).cast<Map<String, dynamic>>();
    if (history.isEmpty) return const Center(child: Text('No history yet.'));

    final primary = Theme.of(context).colorScheme.primary;
    const grey = Color(0xFF667085);

    // Chat messages sit between the status changes; "Current step" is still the latest status change, whatever was
    // said after it. (Older servers send no `kind`: every row is then a status change.)
    final current = history.lastIndexWhere((h) => h['kind'] != 'comment');

    return ListView(
      padding: const EdgeInsets.all(14),
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 6),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFFEAECF0))),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Timeline', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            for (var i = 0; i < history.length; i++)
              history[i]['kind'] == 'comment'
                  ? _comment(history[i], last: i == history.length - 1, grey: grey)
                  : _step(
                      history[i],
                      last: i == current,
                      lastRow: i == history.length - 1,
                      primary: primary,
                      grey: grey,
                    ),
          ]),
        ),
      ],
    );
  }

  /// A chat message in the timeline: who said it, when, and the text.
  Widget _comment(Map<String, dynamic> h, {required bool last, required Color grey}) {
    final at = h['created_at'] == null ? null : DateTime.tryParse(h['created_at'] as String)?.toLocal();
    final text = (h['note'] ?? '').toString().trim();
    final by = (h['by'] ?? 'Someone').toString();
    final role = (h['role'] ?? '').toString().replaceAll('_', ' ');
    final files = (h['attachments'] as num?)?.toInt() ?? 0;

    return IntrinsicHeight(
      key: Key('history-comment-${h['created_at']}'),
      child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SizedBox(
          width: 30,
          child: Column(children: [
            const Icon(Icons.chat_bubble_outline_rounded, size: 20, color: Color(0xFF667085)),
            if (!last) Expanded(child: Container(width: 1.5, margin: const EdgeInsets.symmetric(vertical: 4), color: const Color(0xFFD0D5DD))),
          ]),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: last ? 12 : 18),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(at == null ? '' : DateFormat('dd/MM/yyyy, h:mm a').format(at), style: TextStyle(fontSize: 12, color: grey)),
              const SizedBox(height: 2),
              Text(role.isEmpty ? by : '$by · ${role[0].toUpperCase()}${role.substring(1)}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFF344054))),
              const SizedBox(height: 4),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
                decoration: BoxDecoration(color: const Color(0xFFF4F5F8), borderRadius: BorderRadius.circular(12)),
                child: Text(
                  text.isEmpty ? (files > 0 ? 'Sent ${files == 1 ? 'a file' : '$files files'}' : 'Message') : text,
                  style: const TextStyle(fontSize: 13.5, color: Color(0xFF344054)),
                ),
              ),
              if (text.isNotEmpty && files > 0)
                Padding(padding: const EdgeInsets.only(top: 3), child: Text('+ ${files == 1 ? '1 file' : '$files files'}', style: TextStyle(fontSize: 12, color: grey))),
            ]),
          ),
        ),
      ]),
    );
  }

  Widget _step(Map<String, dynamic> h, {required bool last, required bool lastRow, required Color primary, required Color grey}) {
    final at = h['created_at'] == null ? null : DateTime.tryParse(h['created_at'] as String)?.toLocal();
    final to = h['to_status'] as String?;
    final note = (h['note'] ?? '').toString();
    final color = last ? primary : const Color(0xFF101828);

    return IntrinsicHeight(
      child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        // the icon, and the line down to the next step
        SizedBox(
          width: 30,
          child: Column(children: [
            Icon(last ? Icons.visibility_outlined : _icon(to), size: 22, color: color),
            if (!lastRow) Expanded(child: Container(width: 1.5, margin: const EdgeInsets.symmetric(vertical: 4), color: const Color(0xFFD0D5DD))),
          ]),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: lastRow ? 12 : 18),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(last ? 'Current step' : (at == null ? '' : DateFormat('dd/MM/yyyy, h:mm a').format(at)), style: TextStyle(fontSize: 12, color: grey)),
              const SizedBox(height: 2),
              Text(to == null ? 'Updated' : statusLabel(to), style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: last ? primary : const Color(0xFF101828))),
              if (note.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 2), child: Text(note, style: const TextStyle(fontSize: 13.5, color: Color(0xFF344054)))),
              if (last && at != null) Padding(padding: const EdgeInsets.only(top: 2), child: Text('Since ${DateFormat('dd/MM/yyyy, h:mm a').format(at)}', style: TextStyle(fontSize: 12, color: grey))),
            ]),
          ),
        ),
      ]),
    );
  }
}

/// The task's conversation on its own page, opened from the chat icon beside the ticket's name.
class ChatScreen extends StatelessWidget {
  const ChatScreen({super.key, required this.taskId, required this.ticket});

  final int taskId;
  final String ticket;

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: const Color(0xFFF4F5F8),
        appBar: AppBar(
          titleSpacing: 0,
          title: Row(children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(shape: BoxShape.circle, color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)),
              child: Icon(Icons.forum_rounded, size: 19, color: Theme.of(context).colorScheme.primary),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Task chat', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, height: 1.1)),
                Text(ticket, style: const TextStyle(fontSize: 11.5, color: Color(0xFF667085), fontWeight: FontWeight.w600)),
              ]),
            ),
          ]),
        ),
        body: ChatTab(taskId: taskId),
      );
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
    final primary = Theme.of(context).colorScheme.primary;

    return Column(
      children: [
        Expanded(
          child: comments.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('$e')),
            // Keep showing the last messages while a background refresh runs.
            skipLoadingOnRefresh: true,
            data: (messages) => messages.isEmpty
                ? const Center(
                    child: Padding(
                      padding: EdgeInsets.all(32),
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        Icon(Icons.chat_bubble_outline_rounded, size: 46, color: Colors.black26),
                        SizedBox(height: 10),
                        Text('No messages yet', style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF475467))),
                        SizedBox(height: 2),
                        Text('Start the conversation about this ticket.', style: TextStyle(color: Colors.black45, fontSize: 12.5)),
                      ]),
                    ),
                  )
                : ListView.builder(
                    reverse: true,
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
                    itemCount: messages.length,
                    itemBuilder: (_, i) {
                      final index = messages.length - 1 - i;
                      final m = messages[index];
                      final previous = index > 0 ? messages[index - 1] : null;
                      // A run of messages from one person shows the name and avatar once.
                      return _Bubble(message: m, mine: m.userId == me, firstInRun: previous == null || previous.userId != m.userId);
                    },
                  ),
          ),
        ),
        if (_uploads != null) Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: UploadPanel(controller: _uploads!, showButton: false)),
        // The composer: a floating pill with the field and a round send button.
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 4, 12, 10),
            child: Container(
              padding: const EdgeInsets.fromLTRB(16, 4, 6, 4),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(28),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 14, offset: const Offset(0, 4))],
              ),
              child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                Expanded(
                  child: TextField(
                    key: const Key('chat-input'),
                    controller: _text,
                    minLines: 1,
                    maxLines: 4,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: const InputDecoration(
                      hintText: 'Write a message…',
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      filled: false,
                      isDense: true,
                      contentPadding: EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
                // the paperclip sits beside send, inside the same pill
                if (_uploads != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 2),
                    child: IconButton(
                      key: const Key('add-files'),
                      tooltip: 'Attach files',
                      style: IconButton.styleFrom(minimumSize: const Size(44, 44)),
                      icon: Icon(Icons.attach_file_rounded, size: 23, color: primary),
                      onPressed: () async {
                        final picked = await ref.read(filePickerProvider).pick();
                        await _uploads!.add(picked);
                      },
                    ),
                  ),
                const SizedBox(width: 2),
                Padding(
                  padding: const EdgeInsets.only(bottom: 2),
                  child: IconButton.filled(
                    key: const Key('chat-send'),
                    style: IconButton.styleFrom(backgroundColor: primary, minimumSize: const Size(44, 44)),
                    onPressed: _sending ? null : _send,
                    icon: _sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_rounded, size: 20),
                  ),
                ),
              ]),
            ),
          ),
        ),
      ],
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.mine, this.firstInRun = true});

  final ChatMessage message;
  final bool mine;
  final bool firstInRun;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final at = message.createdAt == null ? '' : DateFormat('d MMM, h:mm a').format(message.createdAt!);
    final fg = mine ? Colors.white : const Color(0xFF101828);
    final initial = message.userName.trim().isEmpty ? '?' : message.userName.trim()[0].toUpperCase();

    final bubble = Container(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
      constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.72),
      decoration: BoxDecoration(
        gradient: mine ? LinearGradient(colors: [theme.colorScheme.primary, const Color(0xFFB5040F)], begin: Alignment.topLeft, end: Alignment.bottomRight) : null,
        color: mine ? null : Colors.white,
        borderRadius: BorderRadius.only(
          topLeft: const Radius.circular(18),
          topRight: const Radius.circular(18),
          bottomLeft: Radius.circular(mine ? 18 : 4),
          bottomRight: Radius.circular(mine ? 4 : 18),
        ),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 6, offset: const Offset(0, 2))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (!mine && firstInRun) Text(message.userName, style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: theme.colorScheme.primary)),
        Text(message.text, style: TextStyle(fontSize: 14.5, height: 1.3, color: fg)),
        for (final a in message.attachments)
          InkWell(
            onTap: a.url.isEmpty ? null : () => launchUrl(Uri.parse(a.url), mode: LaunchMode.externalApplication),
            child: Container(
              margin: const EdgeInsets.only(top: 6),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
              decoration: BoxDecoration(color: (mine ? Colors.white : theme.colorScheme.primary).withValues(alpha: 0.14), borderRadius: BorderRadius.circular(10)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.attach_file_rounded, size: 15, color: mine ? Colors.white : theme.colorScheme.primary),
                const SizedBox(width: 4),
                Flexible(child: Text(a.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: mine ? Colors.white : theme.colorScheme.primary))),
              ]),
            ),
          ),
        const SizedBox(height: 3),
        Align(alignment: Alignment.centerRight, child: Text(at, style: TextStyle(fontSize: 10, color: mine ? Colors.white70 : const Color(0xFF98A2B3)))),
      ]),
    );

    return Padding(
      padding: EdgeInsets.only(top: firstInRun ? 10 : 3),
      child: Row(
        mainAxisAlignment: mine ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!mine) ...[
            // the avatar sits at the foot of the run; the other messages keep its width free
            SizedBox(
              width: 28,
              child: CircleAvatar(radius: 14, backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.12), child: Text(initial, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: theme.colorScheme.primary))),
            ),
            const SizedBox(width: 6),
          ],
          bubble,
        ],
      ),
    );
  }
}
