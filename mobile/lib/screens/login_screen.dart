import 'package:flutter/material.dart';

import '../api.dart';
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
    if (busy) return;

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

    setState(() => error = Api.firstError(res, 'We could not sign you in.'));
  }

  Future<void> _openSignup() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => SignupScreen(
        api: widget.api,
        onRegistered: (email) {
          // Registration returns no token on purpose: the person lands back
          // here and signs in, so one place decides what a valid session is.
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
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Vidlix',
                    style: TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.8,
                      color: VidlixTheme.ink,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Welcome back',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: VidlixTheme.ink),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Sign in with your email or mobile number.',
                    style: TextStyle(color: VidlixTheme.muted),
                  ),
                  const SizedBox(height: 26),
                  if (error != null) NoticeCard(error!, tone: VidlixTheme.danger),
                  TextField(
                    controller: login,
                    autocorrect: false,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(labelText: 'Email or mobile number'),
                  ),
                  const SizedBox(height: 14),
                  PasswordField(
                    controller: password,
                    label: 'Password',
                    textInputAction: TextInputAction.done,
                    onSubmitted: _submit,
                  ),
                  const SizedBox(height: 22),
                  FilledButton(
                    onPressed: busy ? null : _submit,
                    child: busy
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Text('Log in'),
                  ),
                  const SizedBox(height: 10),
                  TextButton(
                    onPressed: busy ? null : _openSignup,
                    child: const Text('New to Vidlix? Create an account'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
