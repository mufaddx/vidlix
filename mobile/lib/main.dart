import 'package:flutter/material.dart';

import 'api.dart';
import 'update.dart';
import 'screens/login_screen.dart';
import 'screens/shell_screen.dart';
import 'theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const VidlixApp());
}

class VidlixApp extends StatelessWidget {
  const VidlixApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Vidlix',
      debugShowCheckedModeBanner: false,
      theme: VidlixTheme.build(),
      home: const Gate(),
    );
  }
}

class Gate extends StatefulWidget {
  const Gate({super.key});

  @override
  State<Gate> createState() => _GateState();
}

class _GateState extends State<Gate> {
  final api = Api();
  bool loading = true;
  bool signedIn = false;

  @override
  void initState() {
    super.initState();
    _boot();
  }

  /// Checked once the first screen is on the display, so a slow or unreachable
  /// server never delays the app opening.
  void _checkForUpdate() {
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;

      await AppUpdate(api).promptIfOutdated(context);
    });
  }

  Future<void> _boot() async {
    final token = await api.token();
    if (token == null) {
      setState(() {
        signedIn = false;
        loading = false;
      });
      _checkForUpdate();

      return;
    }

    // A stored token can be revoked server-side, so confirm it still works
    // rather than assuming the session is live.
    final me = await api.get('/me', auth: true);
    if (!mounted) return;
    if (me['_http'] == 401) {
      await api.setToken(null);
    }
    setState(() {
      signedIn = me['success'] == true;
      loading = false;
    });
    _checkForUpdate();
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (!signedIn) {
      return LoginScreen(
        api: api,
        onAuthed: () => setState(() => signedIn = true),
      );
    }
    return ShellScreen(
      api: api,
      onSignOut: () async {
        await api.post('/auth/logout', {}, auth: true);
        await api.setToken(null);
        if (mounted) setState(() => signedIn = false);
      },
    );
  }
}
