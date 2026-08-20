import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

/// Creating an account from the phone.
///
/// The app could sign in but not sign up, so anyone who downloaded it first
/// had to go and find the website. Registration returns no token: the API
/// creates the account and the person then signs in, which keeps one place
/// deciding what a valid session is.
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

  String role = 'creator';
  String? error;
  bool busy = false;

  static const roles = {
    'creator': 'Creator',
    'editor': 'Editor',
    'brand': 'Brand',
  };

  @override
  void dispose() {
    name.dispose();
    email.dispose();
    mobile.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      busy = true;
      error = null;
    });

    final res = await widget.api.post('/auth/register', {
      'name': name.text.trim(),
      'email': email.text.trim(),
      'mobile': mobile.text.trim(),
      'password': password.text,
      'role': role,
    });

    if (!mounted) return;
    setState(() => busy = false);

    if (res['success'] == true) {
      widget.onRegistered(email.text.trim());
      return;
    }

    setState(() {
      final errors = res['errors'];
      final firstFieldError = errors is Map && errors.values.isNotEmpty
          ? '${(errors.values.first as List).first}'
          : null;
      error = firstFieldError ?? (res['message'] ?? 'Could not create the account').toString();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create an account')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          if (error != null) NoticeCard(error!, tone: VidlixTheme.danger),
          TextField(
            controller: name,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(labelText: 'Your name'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: email,
            keyboardType: TextInputType.emailAddress,
            autocorrect: false,
            decoration: const InputDecoration(labelText: 'Email address'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: mobile,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'Mobile number'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: password,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Password'),
          ),
          const SizedBox(height: 20),
          const Text('I am joining as', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          RadioGroup<String>(
            groupValue: role,
            onChanged: (value) => setState(() => role = value ?? role),
            child: Column(
              children: roles.entries
                  .map((entry) => RadioListTile<String>(
                        value: entry.key,
                        title: Text(entry.value),
                        contentPadding: EdgeInsets.zero,
                      ))
                  .toList(),
            ),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: busy ? null : _submit,
            child: Text(busy ? 'Creating…' : 'Create account'),
          ),
          const SizedBox(height: 16),
          const NoticeCard(
            'You will need to verify your email on the website before money or '
            'manager actions are allowed. Nothing is confirmed here that the '
            'server has not confirmed.',
          ),
        ],
      ),
    );
  }
}
