import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api_client.dart';
import '../../core/auth.dart';
import '../../core/config.dart';
import '../../core/server_settings.dart';
import '../../core/brand_shapes.dart';
import '../../core/theme.dart';
import '../splash/splash_screen.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  /// Tests switch the slowly drifting background off so screens can settle.
  static bool animateBackground = true;

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

  InputDecoration _decoration(String label, IconData icon, {Widget? suffix}) => InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(fontSize: 13.5, color: Color(0xFF9A9DA6), fontWeight: FontWeight.w600),
        floatingLabelStyle: const TextStyle(fontSize: 12, color: Color(0xFF9A9DA6), fontWeight: FontWeight.w700, letterSpacing: 0.4),
        prefixIcon: Icon(icon, size: 20, color: const Color(0xFF2B2B30)),
        suffixIcon: suffix,
        contentPadding: const EdgeInsets.symmetric(vertical: 14),
        enabledBorder: const UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFE6E7EC))),
        border: const UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFE6E7EC))),
        focusedBorder: const UnderlineInputBorder(borderSide: BorderSide(color: adinnRed, width: 1.6)),
      );

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final size = MediaQuery.sizeOf(context);
    final pad = MediaQuery.paddingOf(context);
    // Clean white page, a pair of soft shapes in the corners, large heading, underlined fields, a pill button.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: Stack(
          fit: StackFit.expand,
          children: [
            BrandShapes(animate: LoginScreen.animateBackground),
            SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: size.height),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 480),
                    child: Padding(
                      padding: EdgeInsets.fromLTRB(32, pad.top + 40, 32, 28 + pad.bottom),
                      child: TweenAnimationBuilder<double>(
                        tween: Tween(begin: 0, end: 1),
                        duration: const Duration(milliseconds: 650),
                        curve: Curves.easeOutCubic,
                        builder: (context, v, child) => Opacity(opacity: v, child: Transform.translate(offset: Offset(0, 22 * (1 - v)), child: child)),
                        child: Form(
                          key: _form,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              SizedBox(
                                width: (size.width * 0.42).clamp(130.0, 190.0),
                                child: const AdinnWordmark(t: 3.0),
                              ),
                              SizedBox(height: (size.height * 0.07).clamp(24.0, 70.0)),
                              const Text('Welcome back', style: TextStyle(fontSize: 34, height: 1.1, fontWeight: FontWeight.w900, color: Color(0xFF16161A))),
                              const SizedBox(height: 10),
                              const Text('Sign in to continue to your workspace', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600, color: Color(0xFF9A9DA6))),
                              const SizedBox(height: 34),
                              TextFormField(
                                controller: _login,
                                autofillHints: const [AutofillHints.username],
                                textInputAction: TextInputAction.next,
                                style: const TextStyle(fontWeight: FontWeight.w700),
                                decoration: _decoration('Email, username or employee code', Icons.mail_outline_rounded),
                                validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter your email, username or employee code' : null,
                              ),
                              const SizedBox(height: 10),
                              TextFormField(
                                controller: _password,
                                obscureText: _hide,
                                autofillHints: const [AutofillHints.password],
                                onFieldSubmitted: (_) => _submit(),
                                style: const TextStyle(fontWeight: FontWeight.w700),
                                decoration: _decoration(
                                  'Password',
                                  Icons.lock_outline_rounded,
                                  suffix: IconButton(
                                    tooltip: _hide ? 'Show password' : 'Hide password',
                                    icon: Icon(_hide ? Icons.visibility : Icons.visibility_off, size: 20),
                                    onPressed: () => setState(() => _hide = !_hide),
                                  ),
                                ),
                                validator: (v) => (v == null || v.isEmpty) ? 'Enter your password' : null,
                              ),
                              Align(
                                alignment: Alignment.centerRight,
                                child: TextButton(
                                  key: const Key('forgot-password-link'),
                                  style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 4), minimumSize: const Size(0, 40)),
                                  onPressed: _busy ? null : () => context.push('/forgot-password'),
                                  child: const Text('Forgot password?', style: TextStyle(color: adinnRed, fontWeight: FontWeight.w800, fontSize: 13)),
                                ),
                              ),
                              if (_error != null) ...[
                                const SizedBox(height: 6),
                                Text(_error!, key: const Key('login-error'), style: TextStyle(color: theme.colorScheme.error)),
                              ],
                              const SizedBox(height: 22),
                              Align(
                                alignment: Alignment.centerRight,
                                child: _PillButton(busy: _busy, onPressed: _submit),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            if (AppConfig.showServerSettings)
            Positioned(
              top: pad.top + 4,
              right: 8,
              child: IconButton(
                key: const Key('server-settings'),
                tooltip: 'Server address',
                color: Colors.white,
                icon: const Icon(Icons.dns_outlined),
                onPressed: () => showDialog<void>(context: context, builder: (_) => const ServerDialog()),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// The rounded gradient "Sign in" pill with a soft glow and an arrow.
class _PillButton extends StatelessWidget {
  const _PillButton({required this.busy, required this.onPressed});

  final bool busy;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        gradient: const LinearGradient(colors: [Color(0xFFE30613), Color(0xFFFF5A5F)]),
        boxShadow: const [BoxShadow(color: Color(0x66E30613), blurRadius: 20, offset: Offset(0, 10))],
      ),
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          borderRadius: BorderRadius.circular(30),
          onTap: busy ? null : onPressed,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
            child: busy
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                : const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('Sign in', style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w800, letterSpacing: 1)),
                      SizedBox(width: 12),
                      Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 20),
                    ],
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
  late final TextEditingController _controller = TextEditingController(
    text: ref.read(serverUrlProvider),
  );
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
    if (url == null) {
      setState(() => _error = 'Enter an address like 192.168.1.52:8000 or https://example.com/design-workflow');
    }
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
            decoration: const InputDecoration(
              labelText: 'Address',
              hintText: '192.168.1.52:8000',
            ),
          ),
          const SizedBox(height: 8),
          if (_checking) const LinearProgressIndicator(),
          if (_error != null)
            Text(
              _error!,
              key: const Key('server-error'),
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          if (_ok != null)
            Text(
              _ok!,
              key: const Key('server-ok'),
              style: const TextStyle(color: Colors.green),
            ),
        ],
      ),
      actions: [
        if (!isDefault)
          TextButton(
            key: const Key('server-reset'),
            onPressed: _reset,
            child: const Text('Reset'),
          ),
        TextButton(
          key: const Key('server-test'),
          onPressed: _checking ? null : _test,
          child: const Text('Test'),
        ),
        FilledButton(
          key: const Key('server-save'),
          style: FilledButton.styleFrom(minimumSize: const Size(80, 40)),
          onPressed: _save,
          child: const Text('Save'),
        ),
      ],
    );
  }
}
