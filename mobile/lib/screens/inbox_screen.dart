import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

class InboxScreen extends StatefulWidget {
  const InboxScreen({super.key, required this.api});

  final Api api;

  @override
  State<InboxScreen> createState() => _InboxScreenState();
}

class _InboxScreenState extends State<InboxScreen> {
  List conversations = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final res = await widget.api.get('/inbox', auth: true);
    if (!mounted) return;
    setState(() {
      conversations = Api.listOf(res);
      loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (conversations.isEmpty)
            const NoticeCard('No conversations yet.')
          else
            ...conversations.map((c) => Card(
                  child: ListTile(
                    title: Text('${c['subject'] ?? 'Conversation'}'),
                    subtitle: Text('${c['channel'] ?? ''} · ${c['external_contact']?['email'] ?? 'internal'}'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => ThreadScreen(
                        api: widget.api,
                        uuid: '${c['conversation_uuid']}',
                        subject: '${c['subject'] ?? 'Conversation'}',
                      ),
                    )),
                  ),
                )),
        ],
      ),
    );
  }
}

class ThreadScreen extends StatefulWidget {
  const ThreadScreen({super.key, required this.api, required this.uuid, required this.subject});

  final Api api;
  final String uuid;
  final String subject;

  @override
  State<ThreadScreen> createState() => _ThreadScreenState();
}

class _ThreadScreenState extends State<ThreadScreen> {
  final composer = TextEditingController();
  List messages = [];
  bool loading = true;
  bool sending = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final res = await widget.api.get('/conversations/${widget.uuid}/messages', auth: true);
    if (!mounted) return;
    setState(() {
      messages = Api.listOf(res);
      loading = false;
    });
  }

  Future<void> _send() async {
    final body = composer.text.trim();
    if (body.isEmpty) return;
    setState(() => sending = true);

    final res = await widget.api.post(
      '/conversations/${widget.uuid}/messages',
      {'body': body},
      auth: true,
    );

    if (!mounted) return;
    setState(() => sending = false);

    if (res['success'] == true) {
      composer.clear();
      await _load();
      if (!mounted) return;
      final status = '${Api.mapOf(res)['delivery_status']}';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_statusLine(status))));
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? 'Could not send.').toString())),
      );
    }
  }

  /// Says exactly what happened to the message. "Sent" is never claimed on the
  /// strength of a stored row.
  String _statusLine(String status) => switch (status) {
        'queued' => 'Stored and queued. It counts as delivered only when the provider says so.',
        'accepted' => 'The email provider accepted it for delivery.',
        'delivered' => 'The provider confirmed delivery.',
        'bounced' => 'The provider reported a bounce.',
        'provider_not_configured' => 'Stored. No email provider is configured, so nothing was sent.',
        _ => 'Stored on the conversation.',
      };

  Widget _bubble(Map message) {
    final outbound = message['direction'] == 'outbound';
    return Align(
      alignment: outbound ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        constraints: const BoxConstraints(maxWidth: 320),
        decoration: BoxDecoration(
          color: outbound ? Colors.white : const Color(0xFFEFE9E0),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: VidlixTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${message['body'] ?? ''}'),
            const SizedBox(height: 6),
            Text(
              '${message['direction']} · ${message['delivery_status']}',
              style: const TextStyle(fontSize: 11, color: VidlixTheme.muted),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.subject)),
      body: Column(
        children: [
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator())
                : ListView(
                    padding: const EdgeInsets.all(20),
                    reverse: true,
                    children: messages.map((m) => _bubble(m as Map)).toList(),
                  ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: composer,
                      decoration: const InputDecoration(hintText: 'Write a reply'),
                    ),
                  ),
                  IconButton(
                    onPressed: sending ? null : _send,
                    icon: const Icon(Icons.send),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
