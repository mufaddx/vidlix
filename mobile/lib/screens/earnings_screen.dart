import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

class EarningsScreen extends StatefulWidget {
  const EarningsScreen({super.key, required this.api});

  final Api api;

  @override
  State<EarningsScreen> createState() => _EarningsScreenState();
}

class _EarningsScreenState extends State<EarningsScreen> {
  Map<String, dynamic> data = {};
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final res = await widget.api.get('/earnings', auth: true);
    if (!mounted) return;
    setState(() {
      data = Api.mapOf(res);
      loading = false;
    });
  }

  Future<void> _requestWithdrawal() async {
    final controller = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Request a withdrawal'),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Amount in rupees'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Request')),
        ],
      ),
    );
    if (submitted != true) return;

    final rupees = double.tryParse(controller.text.trim()) ?? 0;
    final res = await widget.api.post(
      '/withdrawals',
      {'amount_minor': (rupees * 100).round()},
      auth: true,
    );

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          res['success'] == true
              ? 'Requested. An admin approves it, then the provider must confirm the transfer.'
              : (res['message'] ?? 'Could not request a withdrawal.').toString(),
        ),
      ),
    );
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());

    final currency = '${data['currency'] ?? 'INR'}';
    final entries = (data['entries'] as List?) ?? const [];
    final withdrawals = (data['withdrawals'] as List?) ?? const [];
    final payoutReady = data['payout_provider_configured'] == true;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Available', style: Theme.of(context).textTheme.labelLarge),
                  Text(
                    Api.money(data['available_minor'], currency),
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Held in escrow ${Api.money(data['reserved_minor'], currency)} · '
                    'paid out ${Api.money(data['withdrawn_minor'], currency)}',
                    style: const TextStyle(color: VidlixTheme.muted),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Every figure here is the sum of ledger entries. No balance is stored separately.',
                    style: TextStyle(color: VidlixTheme.muted, fontSize: 12),
                  ),
                ],
              ),
            ),
          ),
          if (!payoutReady)
            const NoticeCard(
              'Payouts are unavailable: no payout provider is configured. '
              'You can still request a withdrawal, but nothing will transfer until one is.',
            ),
          FilledButton(onPressed: _requestWithdrawal, child: const Text('Request withdrawal')),
          const SectionTitle('Withdrawals'),
          if (withdrawals.isEmpty)
            const NoticeCard('No withdrawal requests yet.')
          else
            ...withdrawals.map((w) => Card(
                  child: ListTile(
                    title: Text('${Api.money(w['amount_minor'], currency)} · ${w['status']}'),
                    subtitle: Text('${w['last_provider_detail'] ?? ''}'),
                  ),
                )),
          const SectionTitle('Ledger'),
          if (entries.isEmpty)
            const NoticeCard('No ledger entries yet.')
          else
            ...entries.map((e) => Card(
                  child: ListTile(
                    dense: true,
                    title: Text('${e['state']} · ${Api.money(e['amount_minor'], '${e['currency']}')}'),
                    subtitle: Text('${e['created_at']}'),
                  ),
                )),
        ],
      ),
    );
  }
}
