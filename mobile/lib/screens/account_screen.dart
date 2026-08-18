import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key, required this.api, required this.onSignOut});

  final Api api;
  final VoidCallback onSignOut;

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  Map<String, dynamic> me = {};
  Map<String, dynamic> instagram = {};
  Map<String, dynamic> managers = {};
  List invoices = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final meRes = await widget.api.get('/me', auth: true);
    final igRes = await widget.api.get('/instagram', auth: true);
    final mgrRes = await widget.api.get('/managers', auth: true);
    final invRes = await widget.api.get('/invoices', auth: true);
    if (!mounted) return;
    setState(() {
      me = Api.mapOf(meRes);
      instagram = Api.mapOf(igRes);
      managers = Api.mapOf(mgrRes);
      invoices = Api.listOf(invRes);
      loading = false;
    });
  }

  Widget _instagramSection() {
    if (instagram['provider_configured'] != true) {
      return const NoticeCard(
        'Instagram is unavailable: the Meta app is not configured on this server. '
        'No follower or reach numbers are shown, because none have been fetched.',
      );
    }

    final insights = (instagram['insights'] as Map?) ?? const {};
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Connection: ${instagram['status'] ?? 'not_connected'}'),
        if (instagram['username'] != null) Text('@${instagram['username']}'),
        Text(
          instagram['last_synced_at'] == null
              ? 'Never synced from the Meta Graph API.'
              : 'Last synced ${instagram['last_synced_at']}.',
          style: const TextStyle(color: VidlixTheme.muted),
        ),
        const SizedBox(height: 8),
        if (insights.isEmpty)
          const NoticeCard('No insights yet. Only values the official Graph API returned are ever shown.')
        else
          ...insights.entries.map((e) => Card(
                child: ListTile(dense: true, title: Text(e.key), trailing: Text('${e.value}')),
              )),
        const Text(
          'Connect or reconnect Instagram from the Vidlix website; the Meta login must run in a browser.',
          style: TextStyle(color: VidlixTheme.muted, fontSize: 12),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());

    final representing = (managers['representing'] as List?) ?? const [];
    final myManagers = (managers['my_managers'] as List?) ?? const [];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text('${me['name'] ?? ''}', style: Theme.of(context).textTheme.titleLarge),
          Text('${me['email'] ?? ''}', style: const TextStyle(color: VidlixTheme.muted)),
          Text('Email verified: ${me['email_verified'] == true ? 'yes' : 'no'}'),
          const SectionTitle('Instagram'),
          _instagramSection(),
          const SectionTitle('Management'),
          if (representing.isEmpty && myManagers.isEmpty)
            const NoticeCard('No active management relationships.')
          else ...[
            ...myManagers.map((r) => Card(
                  child: ListTile(
                    title: Text('Managed by ${r['manager']?['name'] ?? ''}'),
                    subtitle: Text('${r['status']}'),
                  ),
                )),
            ...representing.map((r) => Card(
                  child: ListTile(
                    title: Text('Representing ${r['creator']?['name'] ?? ''}'),
                    subtitle: Text('${r['status']}'),
                  ),
                )),
          ],
          const SectionTitle('Invoices'),
          if (invoices.isEmpty)
            const NoticeCard('No invoices yet.')
          else
            ...invoices.map((i) => Card(
                  child: ListTile(
                    title: Text('${i['invoice_number']}'),
                    subtitle: Text('${i['status']} · ${Api.money(i['total_minor'], '${i['currency']}')}'),
                  ),
                )),
          const SizedBox(height: 24),
          const NoticeCard(
            'This app talks only to the Vidlix HTTPS API. It never connects to MySQL, and it '
            'never shows a payment, insight, or email as confirmed unless the server says a '
            'provider confirmed it.',
          ),
          OutlinedButton(onPressed: widget.onSignOut, child: const Text('Log out')),
        ],
      ),
    );
  }
}
