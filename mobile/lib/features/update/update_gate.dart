import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/app_update.dart';

/// Wraps the whole app. When the installed build is older than the server's minimum, the app is frozen
/// behind a full-screen "Update required" page; a merely-outdated build gets a dismissible bar instead.
/// Installing the new APK restarts the app on the new build, which then passes straight through.
class UpdateGate extends ConsumerStatefulWidget {
  const UpdateGate({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends ConsumerState<UpdateGate> with WidgetsBindingObserver {
  bool _dismissed = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Coming back from the background (or from the download) re-checks the release.
    if (state == AppLifecycleState.resumed) ref.invalidate(appUpdateProvider);
  }

  @override
  Widget build(BuildContext context) {
    final info = ref.watch(appUpdateProvider).asData?.value;
    final build = ref.watch(appBuildProvider).asData?.value;

    if (info == null || build == null) return widget.child;

    if (info.mustUpdate(build)) return _UpdateRequired(info: info);

    if (info.canUpdate(build) && !_dismissed) {
      return Column(children: [
        Material(
          color: const Color(0xFFFFF4E5),
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              child: Row(children: [
                const Icon(Icons.system_update, size: 18, color: Color(0xFF9A6700)),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Update available${info.latestVersion == null ? '' : ' (v${info.latestVersion})'}',
                    key: const Key('update-available'),
                    style: const TextStyle(fontSize: 13, color: Colors.black87, fontWeight: FontWeight.w600),
                  ),
                ),
                TextButton(key: const Key('update-soft'), onPressed: info.url == null ? null : () => _open(info.url!), child: const Text('Update')),
                IconButton(key: const Key('update-dismiss'), icon: const Icon(Icons.close, size: 18), onPressed: () => setState(() => _dismissed = true)),
              ]),
            ),
          ),
        ),
        Expanded(child: widget.child),
      ]);
    }

    return widget.child;
  }
}

Future<void> _open(String url) async {
  final uri = Uri.tryParse(url);
  if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
}

/// The frozen screen: nothing behind it can be reached (back is disabled too).
class _UpdateRequired extends ConsumerWidget {
  const _UpdateRequired({required this.info});

  final AppUpdateInfo info;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);

    return PopScope(
      canPop: false,
      child: Scaffold(
        key: const Key('update-required'),
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Icon(Icons.system_update, size: 72, color: theme.colorScheme.primary),
              const SizedBox(height: 20),
              Text('Update required', textAlign: TextAlign.center, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 10),
              Text(
                'A new version of Adinn Tasks${info.latestVersion == null ? '' : ' (v${info.latestVersion})'} is available. Please update to keep using the app.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium,
              ),
              if (info.notes != null) ...[
                const SizedBox(height: 14),
                Text(info.notes!, key: const Key('update-notes'), textAlign: TextAlign.center, style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54)),
              ],
              const SizedBox(height: 28),
              FilledButton.icon(
                key: const Key('update-now'),
                onPressed: info.url == null ? null : () => _open(info.url!),
                icon: const Icon(Icons.download),
                label: const Text('Update now'),
              ),
              if (info.url == null) ...[
                const SizedBox(height: 10),
                Text(
                  "The update link isn't set up yet. Please contact your administrator.",
                  key: const Key('update-no-link'),
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.error),
                ),
              ],
              const SizedBox(height: 8),
              TextButton(key: const Key('update-recheck'), onPressed: () => ref.invalidate(appUpdateProvider), child: const Text("I've updated — check again")),
            ]),
          ),
        ),
      ),
    );
  }
}
