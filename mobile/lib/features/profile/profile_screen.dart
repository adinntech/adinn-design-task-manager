import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../core/app_update.dart';
import '../../core/app_lock.dart';
import '../../core/auth.dart';
import '../../core/models.dart';
import '../lock/lock_gate.dart';

/// What `/profile` returns: the editable details plus the vertical choices.
class ProfileData {
  ProfileData({
    required this.name,
    required this.username,
    required this.employeeCode,
    required this.roleName,
    required this.email,
    required this.phone,
    required this.lastLogin,
    required this.pushEnabled,
    required this.hasVerticals,
    required this.hasSkills,
    required this.verticals,
    required this.skills,
    required this.options,
  });

  final String name, username, employeeCode, roleName, email, phone;
  final DateTime? lastLogin;
  final bool pushEnabled, hasVerticals, hasSkills;
  final List<String> verticals, skills;
  final Map<String, String> options; // key -> label

  factory ProfileData.fromJson(Map<String, dynamic> j) {
    final p = (j['profile'] as Map).cast<String, dynamic>();
    String s(String k) => (p[k] ?? '') as String;
    return ProfileData(
      name: s('name'),
      username: s('username'),
      employeeCode: s('employee_code'),
      roleName: s('role_name'),
      email: s('email'),
      phone: s('phone_number'),
      lastLogin: p['last_login_at'] == null
          ? null
          : DateTime.tryParse(p['last_login_at'] as String)?.toLocal(),
      pushEnabled: p['web_push_enabled'] == true,
      hasVerticals: p['has_verticals'] == true,
      hasSkills: p['has_skills'] == true,
      verticals: [
        for (final v in (p['experienced_verticals'] as List? ?? [])) '$v',
      ],
      skills: [for (final v in (p['skills'] as List? ?? [])) '$v'],
      options: {
        for (final e in ((j['vertical_options'] as Map?) ?? {}).entries)
          '${e.key}': '${e.value}',
      },
    );
  }
}

// No automatic retry: the screen has its own Retry button, and a silent back-off timer would outlive the screen.
final profileProvider = FutureProvider.autoDispose<ProfileData>(
  (ref) async =>
      ProfileData.fromJson(await ref.watch(apiClientProvider).get('/profile')),
  retry: (_, _) => null,
);

/// My Profile, laid out for a phone: one card per section, one column of fields, full-width buttons. The same details and
/// rules as the web page (name, username, employee code, role, phone, password, push, verticals and skills).
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  final _name = TextEditingController();
  final _username = TextEditingController();
  final _code = TextEditingController();
  final _role = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _skill = TextEditingController();

  bool _showCurrent = false;
  bool _showPassword = false;
  bool _showConfirm = false;
  bool _filled = false;
  bool _push = false;
  List<String> _verticals = [];
  List<String> _skills = [];
  String? _pickedVertical;
  DateTime? _lastLogin;

  final _busy = <String>{};
  final _errors = <String, String>{};

  @override
  void initState() {
    super.initState();
    // Until the full profile arrives (or if it can't), the saved login supplies what it knows.
    final u = ref.read(authProvider).user;
    _name.text = u?.name ?? '';
    _role.text = u?.roleName ?? '';
    _email.text = u?.email ?? '';
  }

  @override
  void dispose() {
    for (final c in [
      _name,
      _username,
      _code,
      _role,
      _email,
      _phone,
      _current,
      _password,
      _confirm,
      _skill,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  void _fill(ProfileData p) {
    _name.text = p.name;
    _username.text = p.username;
    _code.text = p.employeeCode;
    _role.text = p.roleName;
    _email.text = p.email;
    _phone.text = p.phone;
    _lastLogin = p.lastLogin;
    _push = p.pushEnabled;
    _verticals = [...p.verticals];
    _skills = [...p.skills];
    _filled = true;
  }

  void _toast(String text) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

  Future<void> _submit(
    String id,
    Future<Map<String, dynamic>> Function() call, {
    void Function(Map<String, dynamic> json)? onDone,
  }) async {
    setState(() {
      _busy.add(id);
      _errors.clear();
    });
    try {
      final json = await call();
      onDone?.call(json);
      if (json['message'] is String) _toast(json['message'] as String);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _errors.addAll(e.fieldErrors));
        if (e.fieldErrors.isEmpty) _toast(e.message);
      }
    } finally {
      if (mounted) setState(() => _busy.remove(id));
    }
  }

  Future<void> _saveDetails() => _submit(
    'details',
    () => ref
        .read(apiClientProvider)
        .patch(
          '/profile/basic-info',
          data: {
            'name': _name.text.trim(),
            'username': _username.text.trim(),
            'employee_code': _code.text.trim(),
            'role_name': _role.text.trim(),
            'phone_number': _phone.text.trim(),
          },
        ),
    onDone: (json) {
      final p = ProfileData.fromJson(json);
      if (mounted) setState(() => _fill(p));
      final u = ref.read(authProvider).user;
      if (u != null) {
        ref.read(authProvider.notifier).setUser(AppUser(id: u.id, name: p.name, email: u.email, role: u.role, roleName: p.roleName, isRequestApprover: u.isRequestApprover));
      }
    },
  );

  Future<void> _savePassword() => _submit(
    'password',
    () => ref
        .read(apiClientProvider)
        .patch(
          '/profile/password',
          data: {
            'current_password': _current.text,
            'password': _password.text,
            'password_confirmation': _confirm.text,
          },
        ),
    onDone: (_) => setState(() {
      _current.clear();
      _password.clear();
      _confirm.clear();
    }),
  );

  Future<void> _setPush(bool on) async {
    final before = _push;
    setState(() => _push = on);
    try {
      await ref
          .read(apiClientProvider)
          .patch('/profile/notifications', data: {'web_push_enabled': on});
      if (mounted) _toast(on ? 'Push notifications are on.' : 'Push notifications are off.');
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _push = before);
        _toast(e.message);
      }
    }
  }

  Future<void> _saveVerticals() => _submit(
    'verticals',
    () => ref
        .read(apiClientProvider)
        .patch(
          '/profile/verticals',
          data: {'experienced_verticals': _verticals, 'skills': _skills},
        ),
    onDone: (json) {
      final p = ProfileData.fromJson(json);
      if (!mounted) return;
      setState(() {
        _verticals = [...p.verticals];
        _skills = [...p.skills];
      });
    },
  );

  void _addSkill() {
    final v = _skill.text.trim();
    if (v.isEmpty) return;
    if (!_skills.any((s) => s.toLowerCase() == v.toLowerCase())) {
      setState(() => _skills.add(v));
    }
    _skill.clear();
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user;
    final profile = ref.watch(profileProvider);

    ref.listen(profileProvider, (_, next) {
      // Fill the form once, the first time the full profile arrives (never over what is being typed).
      next.whenData((p) {
        if (!_filled) setState(() => _fill(p));
      });
    });
    if (!_filled) {
      profile.whenData(_fill); // already cached when this screen opens
    }

    final data = profile.asData?.value;
    final primary = Theme.of(context).colorScheme.primary;

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Profile'),
        actions: [
          // Push notifications on / off: one tap, saved at once.
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: IconButton(
              key: const Key('push-toggle'),
              tooltip: _push ? 'Push notifications are on: tap to turn off' : 'Push notifications are off: tap to turn on',
              style: IconButton.styleFrom(backgroundColor: (_push ? primary : const Color(0xFF98A2B3)).withValues(alpha: 0.12)),
              icon: Icon(_push ? Icons.notifications_active_rounded : Icons.notifications_off_rounded, size: 22, color: _push ? primary : const Color(0xFF667085)),
              onPressed: data == null ? null : () => _setPush(!_push),
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          _filled = false;
          ref.invalidate(profileProvider);
          await ref
              .read(profileProvider.future)
              .catchError(
                (_) => ProfileData.fromJson({
                  'profile': {},
                  'vertical_options': {},
                }),
              );
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 28),
          children: [
            _header(user, primary, data),
            if (profile.hasError)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFFAEB),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFFEDF89)),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.cloud_off_rounded,
                        size: 20,
                        color: Color(0xFFB54708),
                      ),
                      const SizedBox(width: 10),
                      const Expanded(
                        child: Text(
                          'Could not load your full profile.',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      TextButton(
                        key: const Key('profile-retry'),
                        onPressed: () => ref.invalidate(profileProvider),
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 12),
            _section(
              icon: Icons.badge_rounded,
              title: 'Profile details',
              subtitle: 'Your e-mail is fixed and cannot be changed here.',
              children: [
                _field('name', 'Full Name', _name, Icons.person_rounded),
                _pair(
                  _field(
                    'username',
                    'Username',
                    _username,
                    Icons.alternate_email_rounded,
                  ),
                  _field(
                    'employee_code',
                    'Employee Code',
                    _code,
                    Icons.tag_rounded,
                  ),
                ),
                _field('role_name', 'Role Name', _role, Icons.work_rounded),
                _field(
                  'email',
                  'Email Address',
                  _email,
                  Icons.mail_rounded,
                  readOnly: true,
                ),
                _field(
                  'phone_number',
                  'Phone Number',
                  _phone,
                  Icons.phone_rounded,
                  keyboard: TextInputType.phone,
                ),
                _readOnlyRow(
                  'Last Login',
                  _lastLogin == null
                      ? '—'
                      : DateFormat('dd MMM yyyy • hh:mm a').format(_lastLogin!),
                  Icons.history_rounded,
                ),
                const SizedBox(height: 14),
                _save(
                  'save-details',
                  'Save Profile Details',
                  'details',
                  _saveDetails,
                ),
              ],
            ),
            const SizedBox(height: 12),
            _section(
              icon: Icons.lock_rounded,
              title: 'Password',
              subtitle: 'Enter your current password, then a new one of at least 8 characters.',
              children: [
                _field(
                  'current_password',
                  'Current Password',
                  _current,
                  Icons.lock_outline_rounded,
                  obscure: !_showCurrent,
                  onToggle: () => setState(() => _showCurrent = !_showCurrent),
                  showing: _showCurrent,
                ),
                _field(
                  'password',
                  'New Password',
                  _password,
                  Icons.key_rounded,
                  obscure: !_showPassword,
                  onToggle: () =>
                      setState(() => _showPassword = !_showPassword),
                  showing: _showPassword,
                ),
                _field(
                  'password_confirmation',
                  'Confirm New Password',
                  _confirm,
                  Icons.key_rounded,
                  obscure: !_showConfirm,
                  onToggle: () => setState(() => _showConfirm = !_showConfirm),
                  showing: _showConfirm,
                ),
                const SizedBox(height: 14),
                _save(
                  'save-password',
                  'Update Password',
                  'password',
                  _savePassword,
                ),
              ],
            ),
            const SizedBox(height: 12),
            _section(
              icon: Icons.fingerprint_rounded,
              title: 'App lock',
              subtitle: 'Ask for your fingerprint, face or a 4-digit PIN whenever the app is opened.',
              children: [
                Consumer(
                  builder: (context, ref, _) {
                    final lock = ref.watch(appLockProvider);

                    // Its own Material: the card around it is painted, and a ListTile draws on the nearest one.
                    return Material(
                      color: Colors.transparent,
                      child: SwitchListTile(
                        key: const Key('app-lock-switch'),
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Lock the app', style: TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text(lock.enabled ? (lock.biometric ? 'PIN and fingerprint / face' : 'PIN only') : 'Off'),
                        value: lock.enabled,
                        onChanged: (on) => on ? _turnLockOn() : _turnLockOff(),
                      ),
                    );
                  },
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (data != null && data.hasVerticals) ...[
              const SizedBox(height: 12),
              _verticalsSection(data),
            ],
            const SizedBox(height: 12),
            _about(),
          ],
        ),
      ),
    );
  }

  /// Same PIN setup as the first-time offer, opened as a page.
  void _turnLockOn() => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LockSetupScreen(fromProfile: true)));

  /// Turning the lock off needs the current PIN, so a borrowed, unlocked phone cannot quietly remove it.
  Future<void> _turnLockOff() async {
    final pin = TextEditingController();
    String? error;

    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialog) => AlertDialog(
          title: const Text('Turn off app lock'),
          content: TextField(
            key: const Key('lock-off-pin'),
            controller: pin,
            autofocus: true,
            obscureText: true,
            keyboardType: TextInputType.number,
            maxLength: 4,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: InputDecoration(labelText: 'Enter your PIN', errorText: error, counterText: ''),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: const Text('Cancel')),
            FilledButton(
              key: const Key('lock-off-confirm'),
              onPressed: () {
                if (ref.read(appLockProvider.notifier).verifyPin(pin.text)) {
                  Navigator.of(dialogContext).pop(true);
                } else {
                  setDialog(() => error = 'That is not your PIN.');
                }
              },
              child: const Text('Turn off'),
            ),
          ],
        ),
      ),
    );
    pin.dispose();

    if (ok == true) await ref.read(appLockProvider.notifier).reset();
  }

  Widget _header(AppUser? user, Color primary, ProfileData? data) {
    final name = user?.name ?? '';
    // Small facts under the name, once the full profile has loaded.
    final facts = <(IconData, String)>[
      if (data != null && data.employeeCode.isNotEmpty) (Icons.badge_outlined, 'ID ${data.employeeCode}'),
      if (data?.lastLogin != null) (Icons.history_rounded, 'Last login ${DateFormat('d MMM, h:mm a').format(data!.lastLogin!)}'),
    ];

    Widget circle(double size, double alpha) => Container(width: size, height: size, decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: alpha)));

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: LinearGradient(colors: [primary, const Color(0xFF9E030D)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        boxShadow: [BoxShadow(color: primary.withValues(alpha: 0.25), blurRadius: 14, offset: const Offset(0, 7))],
      ),
      child: Stack(
        children: [
          Positioned(top: -42, right: -28, child: circle(140, 0.08)),
          Positioned(bottom: -54, left: -26, child: circle(120, 0.06)),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    // the avatar sits in a soft ring
                    Container(
                      padding: const EdgeInsets.all(3),
                      decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: 0.28)),
                      child: CircleAvatar(
                        radius: 30,
                        backgroundColor: Colors.white,
                        child: Text(name.isNotEmpty ? name[0].toUpperCase() : '?', style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: primary)),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800, letterSpacing: -0.3, color: Colors.white)),
                          const SizedBox(height: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
                            child: Text(user?.roleLabel ?? '', style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: Colors.white)),
                          ),
                        ],
                      ),
                    ),
                    // Sign out lives up here, where it is always in reach, instead of at the end of a long page.
                    TextButton.icon(
                      key: const Key('sign-out'),
                      style: TextButton.styleFrom(
                        foregroundColor: Colors.white,
                        backgroundColor: Colors.white.withValues(alpha: 0.16),
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        minimumSize: const Size(0, 36),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      onPressed: () => ref.read(authProvider.notifier).logout(),
                      icon: const Icon(Icons.logout_rounded, size: 17),
                      label: const Text('Sign out', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5)),
                    ),
                  ],
                ),
                if (facts.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final (icon, text) in facts)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.16), borderRadius: BorderRadius.circular(12)),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(icon, size: 14, color: Colors.white70),
                              const SizedBox(width: 6),
                              Flexible(child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: Colors.white))),
                            ],
                          ),
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Which version of the app this is, and whether a newer one has been released.
  Widget _about() => Consumer(
        builder: (context, ref, _) {
          final version = ref.watch(appVersionProvider).asData?.value;
          final build = ref.watch(appBuildProvider).asData?.value;
          final info = ref.watch(appUpdateProvider).asData?.value;
          final newer = build != null && info != null && info.canUpdate(build);
          final status = newer ? const Color(0xFFB54708) : const Color(0xFF067647);

          return _section(
            icon: Icons.info_outline_rounded,
            title: 'About',
            subtitle: 'Adinn Design Tasks',
            children: [
              Row(
                children: [
                  const Expanded(child: Text('Version', style: TextStyle(fontSize: 13, color: Color(0xFF667085), fontWeight: FontWeight.w600))),
                  Text(
                    version == null ? '—' : (build == null ? version : '$version (build $build)'),
                    key: const Key('about-version'),
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  const Expanded(child: Text('Updates', style: TextStyle(fontSize: 13, color: Color(0xFF667085), fontWeight: FontWeight.w600))),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(color: status.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                    child: Text(
                      newer ? 'Update available${info.latestVersion == null ? '' : ' · v${info.latestVersion}'}' : 'Up to date',
                      key: const Key('about-update'),
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: status),
                    ),
                  ),
                  TextButton(
                    key: const Key('about-check'),
                    onPressed: () => ref.invalidate(appUpdateProvider),
                    child: const Text('Check', style: TextStyle(fontWeight: FontWeight.w800)),
                  ),
                ],
              ),
            ],
          );
        },
      );

  /// A card for one group of settings: a small icon badge, the title, then a hairline before the fields.
  Widget _section({
    required IconData icon,
    required String title,
    String? subtitle,
    required List<Widget> children,
  }) {
    final primary = Theme.of(context).colorScheme.primary;

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFEAECF0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(color: primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
                child: Icon(icon, size: 18, color: primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, letterSpacing: -0.2, color: Color(0xFF101828))),
                    if (subtitle != null) Padding(padding: const EdgeInsets.only(top: 1), child: Text(subtitle, style: const TextStyle(fontSize: 11.5, height: 1.3, color: Color(0xFF667085)))),
                  ],
                ),
              ),
            ],
          ),
          const Padding(padding: EdgeInsets.symmetric(vertical: 14), child: Divider(height: 1, color: Color(0xFFF2F4F7))),
          ...children,
        ],
      ),
    );
  }

  /// The one look every input shares: a soft grey fill, a hairline border that turns red on focus, no icons inside.
  InputDecoration _deco({String? hint, String? error, bool readOnly = false, Widget? suffix}) {
    final primary = Theme.of(context).colorScheme.primary;
    OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: c, width: w));

    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w500, color: Color(0xFF98A2B3)),
      errorText: error,
      isDense: true,
      filled: true,
      fillColor: readOnly ? const Color(0xFFF2F4F7) : const Color(0xFFF9FAFB),
      suffixIcon: suffix,
      contentPadding: const EdgeInsets.symmetric(vertical: 13, horizontal: 14),
      border: border(const Color(0xFFEAECF0)),
      enabledBorder: border(const Color(0xFFEAECF0)),
      focusedBorder: border(primary, 1.4),
      errorBorder: border(const Color(0xFFFDA29B)),
      focusedErrorBorder: border(const Color(0xFFD92D20), 1.4),
    );
  }

  /// A small caption above an input, with a quiet icon: "label above, value below", as in a form on paper.
  Widget _labeled(String label, IconData icon, Widget input) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 14, color: const Color(0xFF98A2B3)),
              const SizedBox(width: 6),
              Flexible(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF475467)))),
            ],
          ),
          const SizedBox(height: 6),
          input,
        ],
      );

  /// Two short fields side by side.
  Widget _pair(Widget left, Widget right) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [Expanded(child: left), const SizedBox(width: 12), Expanded(child: right)],
      );

  Widget _field(
    String id,
    String label,
    TextEditingController controller,
    IconData icon, {
    bool readOnly = false,
    bool obscure = false,
    VoidCallback? onToggle,
    bool showing = false,
    TextInputType? keyboard,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: _labeled(
      label,
      icon,
      TextField(
        key: Key('profile-$id'),
        controller: controller,
        readOnly: readOnly,
        obscureText: obscure,
        keyboardType: keyboard,
        style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600, color: readOnly ? const Color(0xFF667085) : const Color(0xFF101828)),
        decoration: _deco(
          hint: readOnly ? null : 'Not set',
          error: _errors[id],
          readOnly: readOnly,
          suffix: readOnly
              ? const Icon(Icons.lock_outline_rounded, size: 17, color: Color(0xFF98A2B3))
              : onToggle == null
                  ? null
                  : TextButton(
                      onPressed: onToggle,
                      child: Text(showing ? 'Hide' : 'Show', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
                    ),
        ),
      ),
    ),
  );

  Widget _readOnlyRow(String label, String value, IconData icon) => _labeled(
        label,
        icon,
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 14),
          decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFEAECF0))),
          child: Text(value, key: const Key('profile-last-login'), style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600, color: Color(0xFF667085))),
        ),
      );

  Widget _save(String key, String label, String id, VoidCallback onPressed) =>
      FilledButton(
        key: Key(key),
        style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
        onPressed: _busy.contains(id) ? null : onPressed,
        child: _busy.contains(id)
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
              )
            : Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
      );


  Widget _verticalsSection(ProfileData data) {
    final addable = {
      for (final e in data.options.entries)
        if (!_verticals.contains(e.key)) e.key: e.value,
    };

    Widget chip(String text, VoidCallback onRemove, Key key) => Chip(
      key: key,
      label: Text(
        text,
        style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
      ),
      onDeleted: onRemove,
      deleteIcon: const Icon(Icons.close_rounded, size: 16),
      visualDensity: VisualDensity.compact,
      backgroundColor: Theme.of(context).colorScheme.primary
          .withValues(alpha: 0.08),
      side: BorderSide.none,
    );

    return _section(
      icon: Icons.auto_awesome_rounded,
      title: data.hasSkills
          ? 'Experienced Verticals & Skills'
          : 'Working Verticals',
      subtitle: data.hasSkills
          ? 'Keep this up to date so BD can see your capability before assigning a task.'
          : 'The verticals you work on.',
      children: [
        _labeled(
          data.hasSkills ? 'Experienced Verticals' : 'Working Verticals',
          Icons.category_rounded,
          KeyedSubtree(
          key: const Key('vertical-add'),
          // a new key per pick, so the field starts empty again instead of holding a value its list no longer offers
          child: DropdownButtonFormField<String>(
            key: ValueKey(_verticals.length),
            initialValue: _pickedVertical,
            isExpanded: true,
            decoration: _deco(hint: 'Add a vertical…'),
            items: [
              for (final e in addable.entries)
                DropdownMenuItem(value: e.key, child: Text(e.value)),
            ],
            onChanged: (v) {
              if (v == null) return;
              setState(() {
                _verticals.add(v);
                _pickedVertical = null;
              });
            },
          ),
        ),
        ),
        if (_verticals.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 10),
            child: Wrap(
              spacing: 6,
              runSpacing: 4,
              children: [
                for (final v in _verticals)
                  chip(
                    data.options[v] ?? v,
                    () => setState(() => _verticals.remove(v)),
                    Key('vertical-$v'),
                  ),
              ],
            ),
          ),
        if (data.hasSkills) ...[
          const SizedBox(height: 14),
          _labeled(
            'Skills',
            Icons.bolt_rounded,
            TextField(
              key: const Key('skill-input'),
              controller: _skill,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _addSkill(),
              style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600),
              decoration: _deco(
                hint: 'Type a skill and press Enter',
                suffix: IconButton(
                  key: const Key('skill-add'),
                  icon: Icon(Icons.add_circle_rounded, color: Theme.of(context).colorScheme.primary),
                  onPressed: _addSkill,
                ),
              ),
            ),
          ),
          if (_skills.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Wrap(
                spacing: 6,
                runSpacing: 4,
                children: [
                  for (final s in _skills)
                    chip(
                      s,
                      () => setState(() => _skills.remove(s)),
                      Key('skill-$s'),
                    ),
                ],
              ),
            ),
        ],
        const SizedBox(height: 14),
        _save(
          'save-verticals',
          'Save Profile Details',
          'verticals',
          _saveVerticals,
        ),
      ],
    );
  }
}
