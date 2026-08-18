import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

class CampaignsScreen extends StatefulWidget {
  const CampaignsScreen({super.key, required this.api});

  final Api api;

  @override
  State<CampaignsScreen> createState() => _CampaignsScreenState();
}

class _CampaignsScreenState extends State<CampaignsScreen> {
  List campaigns = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final res = await widget.api.get('/campaigns');
    if (!mounted) return;
    setState(() {
      campaigns = Api.listOf(res);
      loading = false;
    });
  }

  Future<void> _apply(Map campaign) async {
    final feeController = TextEditingController();
    final messageController = TextEditingController();

    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 20,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Apply to ${campaign['name']}', style: Theme.of(sheetContext).textTheme.titleLarge),
            TextField(
              controller: feeController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Your fee in rupees'),
            ),
            TextField(
              controller: messageController,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Message to the brand'),
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () => Navigator.pop(sheetContext, true),
              child: const Text('Send application'),
            ),
          ],
        ),
      ),
    );

    if (submitted != true) return;

    final rupees = double.tryParse(feeController.text.trim()) ?? 0;
    final res = await widget.api.post(
      '/campaigns/${campaign['id']}/apply',
      {
        'proposed_fee_minor': (rupees * 100).round(),
        'message': messageController.text.trim(),
      },
      auth: true,
    );

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          res['success'] == true
              ? 'Application sent.'
              : (res['message'] ?? 'Could not apply.').toString(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (campaigns.isEmpty)
            const NoticeCard('No published campaigns right now.')
          else
            ...campaigns.map((c) => Card(
                  child: ListTile(
                    title: Text('${c['name'] ?? ''}'),
                    subtitle: Text('${c['status'] ?? ''} · ${c['objective'] ?? ''}'),
                    trailing: TextButton(
                      onPressed: () => _apply(c as Map),
                      child: const Text('Apply'),
                    ),
                  ),
                )),
        ],
      ),
    );
  }
}
