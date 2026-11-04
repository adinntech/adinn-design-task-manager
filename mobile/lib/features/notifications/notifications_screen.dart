import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../tasks/providers.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(notificationsProvider);

    Future<void> run(Future<void> Function() action) async {
      try {
        await action();
        ref.invalidate(notificationsProvider);
      } on ApiException catch (e) {
        if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: data.maybeWhen(data: (d) => d.unread > 0, orElse: () => false) ? () => run(ref.read(roleApiProvider).markAllRead) : null,
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('$e')),
        data: (d) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(notificationsProvider);
            await ref.read(notificationsProvider.future);
          },
          child: d.items.isEmpty
              ? ListView(children: const [SizedBox(height: 120), Center(child: Text('You are all caught up.'))])
              : ListView.separated(
                  itemCount: d.items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    final n = d.items[i];
                    return ListTile(
                      tileColor: n.read ? null : Colors.white,
                      leading: Icon(n.read ? Icons.notifications_none : Icons.notifications_active, color: n.read ? Colors.black45 : Theme.of(context).colorScheme.primary),
                      title: Text(n.title, style: TextStyle(fontWeight: n.read ? FontWeight.normal : FontWeight.w700)),
                      subtitle: Text([n.message, if (n.createdAt != null) DateFormat('d MMM, h:mm a').format(n.createdAt!)].where((s) => s.isNotEmpty).join('\n')),
                      isThreeLine: n.message.isNotEmpty,
                      onTap: () async {
                        if (!n.read) await run(() => ref.read(roleApiProvider).markRead(n.id));
                        if (n.taskId != null && context.mounted) context.push('/task/${n.taskId}');
                      },
                    );
                  },
                ),
        ),
      ),
    );
  }
}
