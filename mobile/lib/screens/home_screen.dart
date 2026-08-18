import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.api});

  final Api api;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, dynamic> me = {};
  List creators = [];
  List applications = [];
  String? error;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final meRes = await widget.api.get('/me', auth: true);
    final people = await widget.api.get('/creators');
    final apps = await widget.api.get('/applications', auth: true);
    if (!mounted) return;
    setState(() {
      me = Api.mapOf(meRes);
      creators = Api.listOf(people);
      applications = Api.listOf(apps);
      error = meRes['success'] == true ? null : (meRes['message'] ?? 'Unable to load').toString();
      loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [NoticeCard(error!, tone: VidlixTheme.danger)],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text('Hello, ${me['name'] ?? ''}', style: Theme.of(context).textTheme.headlineSmall),
          Text(
            (me['roles'] as List?)?.join(' · ') ?? '',
            style: const TextStyle(color: VidlixTheme.muted),
          ),
          if (me['email_verified'] == false)
            const Padding(
              padding: EdgeInsets.only(top: 16),
              child: NoticeCard('Verify your email on the website before starting money or manager actions.'),
            ),
          const SectionTitle('My applications'),
          if (applications.isEmpty)
            const NoticeCard('No campaign applications yet.')
          else
            ...applications.take(5).map((a) => Card(
                  child: ListTile(
                    title: Text('${a['campaign']?['name'] ?? 'Campaign'}'),
                    subtitle: Text('${a['status']} · ${Api.money(a['proposed_fee_minor'])}'),
                  ),
                )),
          const SectionTitle('Creators'),
          if (creators.isEmpty)
            const NoticeCard('No published creators yet.')
          else
            ...creators.take(10).map((c) => Card(
                  child: ListTile(
                    title: Text('${c['display_name'] ?? ''}'),
                    subtitle: Text('@${c['username'] ?? ''}'),
                  ),
                )),
        ],
      ),
    );
  }
}
