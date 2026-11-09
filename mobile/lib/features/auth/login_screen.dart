import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api_client.dart';
import '../../core/auth.dart';
import '../../core/config.dart';
import '../../core/server_settings.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _login = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  bool _hide = true;
  String? _error;

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).login(_login.text, _password.text);
      // The router redirects on the auth state change.
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        actions: [
          IconButton(
            key: const Key('server-settings'),
            tooltip: 'Server address',
            icon: const Icon(Icons.dns_outlined),
            onPressed: () => showDialog<void>(context: context, builder: (_) => const ServerDialog()),
          ),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('Adinn', style: theme.textTheme.displaySmall?.copyWith(color: theme.colorScheme.primary, fontWeight: FontWeight.w800)),
                    Text('Design Task Manager', style: theme.textTheme.titleMedium),
                    const SizedBox(height: 32),
                    TextFormField(
                      controller: _login,
                      autofillHints: const [AutofillHints.username],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(labelText: 'Email, username or employee code'),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter your email, username or employee code' : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _password,
                      obscureText: _hide,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        suffixIcon: IconButton(
                          tooltip: _hide ? 'Show password' : 'Hide password',
                          icon: Icon(_hide ? Icons.visibility : Icons.visibility_off),
                          onPressed: () => setState(() => _hide = !_hide),
                        ),
                      ),
                      validator: (v) => (v == null || v.isEmpty) ? 'Enter your password' : null,
                    ),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        key: const Key('forgot-password-link'),
                        onPressed: _busy ? null : () => context.push('/forgot-password'),
                        child: const Text('Forgot password?'),
                      ),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 4),
                      Text(_error!, key: const Key('login-error'), style: TextStyle(color: theme.colorScheme.error)),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Sign in'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}


/// Lets the app point at a different server (e.g. the PC on the local Wi-Fi) without a rebuild.
class ServerDialog extends ConsumerStatefulWidget {
  const ServerDialog({super.key});

  @override
  ConsumerState<ServerDialog> createState() => _ServerDialogState();
}

class _ServerDialogState extends ConsumerState<ServerDialog> {
  late final TextEditingController _controller = TextEditingController(text: ref.read(serverUrlProvider));
  String? _error;
  String? _ok;
  bool _checking = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  String? _normalized() {
    final url = normalizeServerUrl(_controller.text);
    if (url == null) setState(() => _error = 'Enter an address like 192.168.1.52:8000 or https://example.com/design-workflow');
    return url;
  }

  Future<void> _test() async {
    final url = _normalized();
    if (url == null) return;
    setState(() {
      _checking = true;
      _error = null;
      _ok = null;
    });
    final problem = await ref.read(serverCheckerProvider)(url);
    if (!mounted) return;
    setState(() {
      _checking = false;
      _error = problem;
      _ok = problem == null ? 'Connected.' : null;
    });
  }

  Future<void> _save() async {
    final url = _normalized();
    if (url == null) return;
    await ref.read(serverUrlProvider.notifier).save(url);
    if (mounted) Navigator.pop(context);
  }

  Future<void> _reset() async {
    await ref.read(serverUrlProvider.notifier).reset();
    if (mounted) Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final isDefault = ref.watch(serverUrlProvider) == AppConfig.apiBaseUrl;

    return AlertDialog(
      title: const Text('Server address'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            key: const Key('server-url'),
            controller: _controller,
            keyboardType: TextInputType.url,
            autocorrect: false,
            decoration: const InputDecoration(labelText: 'Address', hintText: '192.168.1.52:8000'),
          ),
          const SizedBox(height: 8),
          if (_checking) const LinearProgressIndicator(),
          if (_error != null) Text(_error!, key: const Key('server-error'), style: TextStyle(color: Theme.of(context).colorScheme.error)),
          if (_ok != null) Text(_ok!, key: const Key('server-ok'), style: const TextStyle(color: Colors.green)),
        ],
      ),
      actions: [
        if (!isDefault) TextButton(key: const Key('server-reset'), onPressed: _reset, child: const Text('Reset')),
        TextButton(key: const Key('server-test'), onPressed: _checking ? null : _test, child: const Text('Test')),
        FilledButton(key: const Key('server-save'), style: FilledButton.styleFrom(minimumSize: const Size(80, 40)), onPressed: _save, child: const Text('Save')),
      ],
    );
  }
}
