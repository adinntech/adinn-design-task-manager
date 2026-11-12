import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api_client.dart';
import '../../core/auth.dart';

/// "Forgot password": ask for the Username / Email / Employee Code, then reset with the OTP that was mailed.
/// Same two steps and rules as the web pages (`POST /password/forgot`, `POST /password/reset`).
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  static const _resendSeconds = 60;

  final _askForm = GlobalKey<FormState>();
  final _resetForm = GlobalKey<FormState>();
  final _login = TextEditingController();
  final _otp = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();

  bool _sent = false;
  bool _busy = false;
  bool _hide = true;
  String? _info;
  String? _error;
  Map<String, String> _fieldErrors = const {};
  int _cooldown = 0;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    _login.dispose();
    _otp.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  void _startCooldown() {
    _timer?.cancel();
    setState(() => _cooldown = _resendSeconds);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return t.cancel();
      setState(() => _cooldown--);
      if (_cooldown <= 0) t.cancel();
    });
  }

  Future<void> _sendOtp() async {
    if (!_sent && !_askForm.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = const {};
    });
    try {
      final json = await ref.read(apiClientProvider).post('/password/forgot', data: {'login': _login.text.trim()});
      if (!mounted) return;
      setState(() {
        _sent = true;
        _info = (json['message'] as String?) ?? 'If these details match an account, an OTP has been emailed.';
      });
      _startCooldown();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reset() async {
    if (!_resetForm.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = const {};
    });
    try {
      await ref.read(apiClientProvider).post('/password/reset', data: {
        'login': _login.text.trim(),
        'otp': _otp.text.trim(),
        'password': _password.text,
        'password_confirmation': _confirm.text,
      });
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      context.pop();
      messenger.showSnackBar(const SnackBar(content: Text('Password reset successfully. Sign in with your new password.')));
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _fieldErrors = e.fieldErrors;
          if (e.fieldErrors.isEmpty) _error = e.message;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(backgroundColor: Colors.transparent, title: const Text('Forgot password')),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Container(
                  alignment: Alignment.center,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  child: CircleAvatar(
                    radius: 30,
                    backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.1),
                    child: Icon(_sent ? Icons.mark_email_read_rounded : Icons.lock_reset_rounded, size: 30, color: theme.colorScheme.primary),
                  ),
                ),
                Text(_sent ? 'Check your email' : 'Reset your password', textAlign: TextAlign.center, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 6),
                Text(
                  _sent
                      ? 'Enter the 6-digit OTP we emailed you and choose a new password.'
                      : 'Enter your Username, Email or Employee Code. If it matches your account, we will email you a 6-digit OTP.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(color: Colors.black54),
                ),
                const SizedBox(height: 24),
                if (!_sent) _askStep(theme) else _resetStep(theme),
              ]),
            ),
          ),
        ),
      ),
    );
  }

  Widget _askStep(ThemeData theme) {
    return Form(
      key: _askForm,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        TextFormField(
          key: const Key('forgot-login'),
          controller: _login,
          autofocus: true,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _sendOtp(),
          decoration: const InputDecoration(labelText: 'Username, email or employee code'),
          validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter your username, email or employee code' : null,
        ),
        if (_error != null) ...[
          const SizedBox(height: 12),
          Text(_error!, key: const Key('forgot-error'), style: TextStyle(color: theme.colorScheme.error)),
        ],
        const SizedBox(height: 20),
        FilledButton(
          key: const Key('forgot-send'),
          onPressed: _busy ? null : _sendOtp,
          child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Send OTP'),
        ),
      ]),
    );
  }

  Widget _resetStep(ThemeData theme) {
    return Form(
      key: _resetForm,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (_info != null)
          Container(
            key: const Key('forgot-info'),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: const Color(0xFFECFDF3), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFB8EFD2))),
            child: Text(_info!, style: const TextStyle(color: Color(0xFF08784B), fontSize: 12.5, fontWeight: FontWeight.w600)),
          ),
        const SizedBox(height: 16),
        TextFormField(
          key: const Key('forgot-otp'),
          controller: _otp,
          keyboardType: TextInputType.number,
          autofillHints: const [AutofillHints.oneTimeCode],
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: 8),
          decoration: InputDecoration(labelText: '6-digit OTP', errorText: _fieldErrors['otp']),
          validator: (v) => (v == null || v.trim().length != 6) ? 'Enter the 6-digit OTP from the email' : null,
        ),
        const SizedBox(height: 14),
        TextFormField(
          key: const Key('forgot-password'),
          controller: _password,
          obscureText: _hide,
          autofillHints: const [AutofillHints.newPassword],
          decoration: InputDecoration(
            labelText: 'New password',
            errorText: _fieldErrors['password'],
            suffixIcon: IconButton(
              tooltip: _hide ? 'Show password' : 'Hide password',
              icon: Icon(_hide ? Icons.visibility : Icons.visibility_off),
              onPressed: () => setState(() => _hide = !_hide),
            ),
          ),
          validator: (v) => (v == null || v.length < 8) ? 'At least 8 characters' : null,
        ),
        const SizedBox(height: 14),
        TextFormField(
          key: const Key('forgot-confirm'),
          controller: _confirm,
          obscureText: _hide,
          autofillHints: const [AutofillHints.newPassword],
          onFieldSubmitted: (_) => _reset(),
          decoration: const InputDecoration(labelText: 'Confirm new password'),
          validator: (v) => v != _password.text ? 'Passwords do not match' : null,
        ),
        if (_error != null) ...[
          const SizedBox(height: 12),
          Text(_error!, key: const Key('forgot-error'), style: TextStyle(color: theme.colorScheme.error)),
        ],
        const SizedBox(height: 20),
        FilledButton(
          key: const Key('forgot-reset'),
          onPressed: _busy ? null : _reset,
          child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Reset password'),
        ),
        const SizedBox(height: 8),
        TextButton(
          key: const Key('forgot-resend'),
          onPressed: (_busy || _cooldown > 0) ? null : _sendOtp,
          child: Text(_cooldown > 0 ? 'Resend OTP in ${_cooldown}s' : 'Resend OTP'),
        ),
        TextButton(
          key: const Key('forgot-restart'),
          onPressed: _busy
              ? null
              : () => setState(() {
                    _sent = false;
                    _error = null;
                    _fieldErrors = const {};
                    _otp.clear();
                  }),
          child: const Text('Use a different username or email'),
        ),
      ]),
    );
  }
}
