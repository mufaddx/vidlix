import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';
import 'terms_screen.dart';

/// Creating an account from the phone.
///
/// Registration returns no token: the API creates the account and the person
/// then signs in, which keeps one place deciding what a valid session is.
class SignupScreen extends StatefulWidget {
  const SignupScreen({super.key, required this.api, required this.onRegistered});

  final Api api;

  /// Called with the email so the sign-in screen can prefill it.
  final void Function(String email) onRegistered;

  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final name = TextEditingController();
  final email = TextEditingController();
  final mobile = TextEditingController();
  final password = TextEditingController();
  final confirm = TextEditingController();

  String role = 'creator';
  bool acceptedTerms = false;
  String? acceptedFor;
  String? error;
  bool busy = false;

  static const roles = {
    'creator': 'Influencer',
    'editor': 'Editor',
    'brand': 'Brand',
  };

  @override
  void dispose() {
    name.dispose();
    email.dispose();
    mobile.dispose();
    password.dispose();
    confirm.dispose();
    super.dispose();
  }

  Future<void> _openTerms() async {
    final accepted = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => TermsScreen(api: widget.api, role: role, roleLabel: roles[role]!),
    ));

    if (!mounted) return;

    if (accepted == true) {
      setState(() {
        acceptedTerms = true;
        acceptedFor = role;
      });
    }
  }

  Future<void> _submit() async {
    if (busy) return;

    if (!acceptedTerms || acceptedFor != role) {
      setState(() => error = 'Please read and accept the ${roles[role]} terms first.');
      return;
    }

    if (password.text != confirm.text) {
      // Caught here as well as on the server, so the answer arrives without a
      // round trip and with the fields still filled in.
      setState(() => error = 'The two passwords do not match.');
      return;
    }

    setState(() {
      busy = true;
      error = null;
    });

    final res = await widget.api.post('/auth/register', {
      'name': name.text.trim(),
      'email': email.text.trim(),
      'mobile': mobile.text.trim(),
      'password': password.text,
      'password_confirmation': confirm.text,
      'role': role,
    });

    if (!mounted) return;
    setState(() => busy = false);

    if (res['success'] == true) {
      widget.onRegistered(email.text.trim());
      return;
    }

    setState(() => error = Api.firstError(res, 'The account could not be created.'));
  }

  Widget _roleTile(MapEntry<String, String> entry) {
    final selected = role == entry.key;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => setState(() {
          role = entry.key;
          // Accepting one role's terms is not accepting another's.
          acceptedTerms = acceptedFor == entry.key;
        }),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            color: selected ? VidlixTheme.accentSoft : VidlixTheme.surface,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: selected ? VidlixTheme.accent : VidlixTheme.line,
              width: selected ? 1.6 : 1,
            ),
          ),
          child: Row(
            children: [
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                color: selected ? VidlixTheme.accent : VidlixTheme.muted,
                size: 22,
              ),
              const SizedBox(width: 12),
              Text(
                entry.value,
                style: TextStyle(
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  color: VidlixTheme.ink,
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create an account')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 32),
          children: [
            if (error != null) NoticeCard(error!, tone: VidlixTheme.danger),
            TextField(
              controller: name,
              textCapitalization: TextCapitalization.words,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(labelText: 'Your name'),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: email,
              keyboardType: TextInputType.emailAddress,
              autocorrect: false,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(labelText: 'Email address'),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: mobile,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(labelText: 'Mobile number'),
            ),
            const SizedBox(height: 14),
            PasswordField(
              controller: password,
              label: 'Password',
              textInputAction: TextInputAction.next,
            ),
            const SizedBox(height: 6),
            const Text(
              'At least 10 characters, with upper and lower case letters and a number.',
              style: TextStyle(color: VidlixTheme.muted, fontSize: 12.5),
            ),
            const SizedBox(height: 14),
            PasswordField(
              controller: confirm,
              label: 'Confirm password',
              textInputAction: TextInputAction.done,
            ),
            const SectionTitle('I am joining as'),
            ...roles.entries.map(_roleTile),
            const SizedBox(height: 6),
            const Text(
              'You can add another role later from your profile.',
              style: TextStyle(color: VidlixTheme.muted, fontSize: 12.5),
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: VidlixTheme.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: acceptedTerms ? VidlixTheme.accent : VidlixTheme.line),
              ),
              child: Row(
                children: [
                  Icon(
                    acceptedTerms ? Icons.check_circle : Icons.circle_outlined,
                    color: acceptedTerms ? VidlixTheme.accent : VidlixTheme.muted,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      acceptedTerms
                          ? 'You accepted the ${roles[role]} terms.'
                          : 'Read the ${roles[role]} terms before creating your account.',
                      style: const TextStyle(color: VidlixTheme.ink, height: 1.4),
                    ),
                  ),
                  TextButton(
                    onPressed: busy ? null : _openTerms,
                    child: Text(acceptedTerms ? 'Read again' : 'Read'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: busy ? null : _submit,
              child: busy
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Text('Create account'),
            ),
          ],
        ),
      ),
    );
  }
}
