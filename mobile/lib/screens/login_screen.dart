import 'package:flutter/material.dart';

import '../api.dart';
import '../config.dart';
import '../theme.dart';
import 'signup_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api, required this.onAuthed});

  final Api api;
  final VoidCallback onAuthed;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final login = TextEditingController();
  final password = TextEditingController();
  String? error;
  bool busy = false;

  @override
  void dispose() {
    login.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      busy = true;
      error = null;
    });

    final res = await widget.api.post('/auth/login', {
      'login': login.text.trim(),
      'password': password.text,
    });

    if (!mounted) return;
    setState(() => busy = false);

    if (res['success'] == true) {
      await widget.api.setToken(Api.mapOf(res)['token'] as String);
      widget.onAuthed();
      return;
    }

    setState(() {
      final errors = res['errors'];
      final firstFieldError = errors is Map && errors.values.isNotEmpty
          ? '${(errors.values.first as List).first}'
          : null;
      error = firstFieldError ?? (res['message'] ?? 'Login failed').toString();
    });
  }

  Future<void> _openSignup() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => SignupScreen(
        api: widget.api,
        onRegistered: (email) {
          // Registration returns no token on purpose, so the person lands back
          // here and signs in - one place decides what a valid session is.
          login.text = email;
          Navigator.of(context).pop();
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Account created. Sign in to continue.')),
          );
        },
      ),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            const SizedBox(height: 32),
            Text('Vidlix', style: Theme.of(context).textTheme.headlineLarge),
            const Text('Creator × Brand × Editor × Manager'),
            const SizedBox(height: 32),
            TextField(
              controller: login,
              autocorrect: false,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'Email or mobile'),
            ),
            TextField(
              controller: password,
              obscureText: true,
              onSubmitted: (_) => busy ? null : _submit(),
              decoration: const InputDecoration(labelText: 'Password'),
            ),
            if (error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(error!, style: const TextStyle(color: VidlixTheme.danger)),
              ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: busy ? null : _submit,
              child: Text(busy ? 'Signing in…' : 'Log in'),
            ),
            const SizedBox(height: 8),
            TextButton(
              onPressed: busy ? null : _openSignup,
              child: const Text('New to Vidlix? Create an account'),
            ),
            const SizedBox(height: 8),
            Text(
              'Signs in against ${Config.apiBase}/api/v1 — the same backend as the website. '
              'The phone never talks to MySQL.',
              style: const TextStyle(color: VidlixTheme.muted, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
