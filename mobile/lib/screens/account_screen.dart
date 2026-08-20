import 'package:flutter/material.dart';

import '../api.dart';
import '../push.dart';
import '../theme.dart';
import 'campaigns_screen.dart';
import 'earnings_screen.dart';

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
  List applications = [];
  List invoices = [];
  bool loading = true;
  bool registering = false;
  String? pushState;

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
    final appRes = await widget.api.get('/applications', auth: true);
    final invRes = await widget.api.get('/invoices', auth: true);
    if (!mounted) return;
    setState(() {
      me = Api.mapOf(meRes);
      instagram = Api.mapOf(igRes);
      managers = Api.mapOf(mgrRes);
      applications = Api.listOf(appRes);
      invoices = Api.listOf(invRes);
      loading = false;
    });
  }

  Widget _pushSection() {
    final state = pushState;

    final line = switch (state) {
      'registered' => 'This device is registered for notifications.',
      'registered_no_provider' =>
        'This device is registered, but the server has no push provider configured, so nothing is delivered yet.',
      'no_token_provider' || 'no_device_token' =>
        'No device token is available in this build, so this device is not registered. Notifications still appear in the app.',
      'rejected' => 'The server would not register this device.',
      _ => 'Not registered yet.',
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        NoticeCard(line),
        OutlinedButton(
          onPressed: registering ? null : _registerDevice,
          child: Text(registering ? 'Registering…' : 'Register this device'),
        ),
      ],
    );
  }

  Future<void> _registerDevice() async {
    setState(() => registering = true);
    final result = await PushRegistration(widget.api).register();
    if (!mounted) return;
    setState(() {
      registering = false;
      pushState = result;
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
          const SectionTitle('Elsewhere in Vidlix'),
          // Campaigns and Earnings are not bottom-bar tabs, so this is how they
          // are reached. Nothing that used to be a tab has been dropped.
          Card(
            child: ListTile(
              leading: const Icon(Icons.campaign_outlined),
              title: const Text('Campaigns'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => Scaffold(
                  appBar: AppBar(title: const Text('Campaigns')),
                  body: CampaignsScreen(api: widget.api),
                ),
              )),
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.account_balance_wallet_outlined),
              title: const Text('Earnings'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => Scaffold(
                  appBar: AppBar(title: const Text('Earnings')),
                  body: EarningsScreen(api: widget.api),
                ),
              )),
            ),
          ),
          const SectionTitle('Notifications'),
          _pushSection(),
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
