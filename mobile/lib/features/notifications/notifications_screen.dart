import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';
import '../tasks/providers.dart';

enum _Filter { all, unread, read }

/// The kind of alert, read from its title, as an icon.
IconData _iconFor(String title) {
  final t = title.toLowerCase();
  if (t.contains('comment')) return Icons.chat_bubble_rounded;
  if (t.contains('split')) return Icons.call_split_rounded;
  if (t.contains('swap')) return Icons.swap_horiz_rounded;
  if (t.contains('declin') || t.contains('reject')) return Icons.cancel_rounded;
  if (t.contains('approv') || t.contains('complete') || t.contains('confirm')) return Icons.check_circle_rounded;
  if (t.contains('assign')) return Icons.person_add_alt_1_rounded;
  if (t.contains('printing')) return Icons.description_rounded;
  if (t.contains('rating')) return Icons.thumb_up_alt_rounded;
  if (t.contains('rework')) return Icons.replay_rounded;
  if (t.contains('status') || t.contains('moved')) return Icons.sync_rounded;
  if (t.contains('update')) return Icons.system_update_rounded;
  return Icons.notifications_active_rounded;
}

class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  _Filter _filter = _Filter.all;
  String _query = '';

  Future<void> _run(Future<void> Function() action) async {
    try {
      await action();
      ref.invalidate(notificationsProvider);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(notificationsProvider);
    final unread = data.maybeWhen(data: (d) => d.unread, orElse: () => 0);

    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      floatingActionButton: unread > 0
          ? FloatingActionButton.extended(
              key: const Key('mark-all-read'),
              onPressed: () => _run(ref.read(roleApiProvider).markAllRead),
              icon: const Icon(Icons.done_all_rounded),
              label: const Text('Mark all read', style: TextStyle(fontWeight: FontWeight.w800)),
              backgroundColor: Theme.of(context).colorScheme.primary,
              foregroundColor: Colors.white,
            )
          : null,
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('$e')),
        data: (d) {
          final items = d.items;
          final readCount = items.where((n) => n.read).length;
          final q = _query.trim().toLowerCase();
          final shown = [
            for (final n in items)
              if ((_filter == _Filter.all || (_filter == _Filter.unread ? !n.read : n.read)) && (q.isEmpty || n.title.toLowerCase().contains(q) || n.message.toLowerCase().contains(q))) n,
          ];

          return Column(children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
              child: Row(children: [
                _pill('all', 'All (${items.length})', Icons.notifications_rounded, _Filter.all),
                const SizedBox(width: 8),
                _pill('unread', 'Unread (${d.unread})', Icons.mark_email_unread_rounded, _Filter.unread),
                const SizedBox(width: 8),
                _pill('read', 'Read ($readCount)', Icons.check_circle_rounded, _Filter.read),
              ]),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
              child: Container(
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(22), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10, offset: const Offset(0, 3))]),
                child: TextField(
                  key: const Key('alerts-search'),
                  onChanged: (v) => setState(() => _query = v),
                  decoration: const InputDecoration(
                    hintText: 'Search notifications...',
                    hintStyle: TextStyle(fontSize: 14),
                    prefixIcon: Icon(Icons.search_rounded),
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    filled: false,
                    isDense: true,
                    contentPadding: EdgeInsets.symmetric(vertical: 13),
                  ),
                ),
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(notificationsProvider);
                  await ref.read(notificationsProvider.future);
                },
                child: shown.isEmpty
                    ? ListView(children: [
                        const SizedBox(height: 90),
                        Center(child: Icon(items.isEmpty ? Icons.notifications_off_outlined : Icons.search_off_rounded, size: 46, color: Colors.black26)),
                        const SizedBox(height: 10),
                        Center(child: Text(items.isEmpty ? 'You are all caught up.' : 'No notifications match.', style: const TextStyle(color: Colors.black45, fontWeight: FontWeight.w700))),
                      ])
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(14, 4, 14, 90),
                        itemCount: shown.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 10),
                        itemBuilder: (_, i) => _card(shown[i]),
                      ),
              ),
            ),
          ]);
        },
      ),
    );
  }

  Widget _pill(String key, String label, IconData icon, _Filter value) {
    final on = _filter == value;
    final primary = Theme.of(context).colorScheme.primary;

    return Expanded(
      child: GestureDetector(
        key: Key('alerts-tab-$key'),
        onTap: () => setState(() => _filter = value),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: on ? primary : Colors.white,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: on ? Colors.transparent : const Color(0xFFD0D5DD)),
            boxShadow: on ? [BoxShadow(color: primary.withValues(alpha: 0.3), blurRadius: 10, offset: const Offset(0, 4))] : null,
          ),
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(icon, size: 16, color: on ? Colors.white : const Color(0xFF667085)),
            const SizedBox(width: 5),
            Flexible(child: FittedBox(fit: BoxFit.scaleDown, child: Text(label, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: on ? Colors.white : const Color(0xFF475467))))),
          ]),
        ),
      ),
    );
  }

  Widget _card(AppNotification n) {
    final primary = Theme.of(context).colorScheme.primary;
    final accent = n.read ? const Color(0xFF667085) : primary;

    return Container(
      key: Key('notif-${n.id}'),
      decoration: BoxDecoration(
        color: n.read ? Colors.white : const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: n.read ? const Color(0xFFEAECF0) : primary.withValues(alpha: 0.25)),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10, offset: const Offset(0, 4))],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () async {
            if (!n.read) await _run(() => ref.read(roleApiProvider).markRead(n.id));
            if (n.taskId != null && mounted) context.push('/task/${n.taskId}');
          },
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Stack(clipBehavior: Clip.none, children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(color: accent.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(14)),
                  child: Icon(_iconFor(n.title), color: accent, size: 23),
                ),
                if (!n.read) Positioned(left: -4, top: -4, child: Container(width: 10, height: 10, decoration: BoxDecoration(color: primary, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 1.5)))),
              ]),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Expanded(child: Text(n.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 15, fontWeight: n.read ? FontWeight.w700 : FontWeight.w800, letterSpacing: -0.2))),
                    if (!n.read)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(color: primary.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                        child: Text('New', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: primary)),
                      ),
                  ]),
                  if (n.message.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 3), child: Text(n.message, maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, height: 1.3, color: Color(0xFF475467)))),
                  if (n.createdAt != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Row(children: [
                        const Icon(Icons.calendar_today_rounded, size: 12, color: Color(0xFF98A2B3)),
                        const SizedBox(width: 5),
                        Text(DateFormat('d MMM, h:mm a').format(n.createdAt!), style: const TextStyle(fontSize: 11.5, color: Color(0xFF667085), fontWeight: FontWeight.w600)),
                      ]),
                    ),
                ]),
              ),
              if (n.taskId != null) const Padding(padding: EdgeInsets.only(left: 4, top: 10), child: Icon(Icons.chevron_right_rounded, color: Color(0xFF98A2B3))),
            ]),
          ),
        ),
      ),
    );
  }
}
