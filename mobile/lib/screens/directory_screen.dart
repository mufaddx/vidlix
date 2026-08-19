import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

/// Browse published creators or editors.
///
/// One widget serves both tabs because the two directories differ only in the
/// endpoint they read and the words on screen; duplicating the list would mean
/// fixing every future bug twice.
class DirectoryScreen extends StatefulWidget {
  const DirectoryScreen({
    super.key,
    required this.api,
    required this.path,
    required this.emptyMessage,
  });

  final Api api;

  /// `/creators` or `/editors`.
  final String path;

  final String emptyMessage;

  @override
  State<DirectoryScreen> createState() => _DirectoryScreenState();
}

class _DirectoryScreenState extends State<DirectoryScreen> {
  List people = [];
  String query = '';
  String? error;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final res = await widget.api.get(widget.path);
    if (!mounted) return;
    setState(() {
      people = Api.listOf(res);
      error = res['success'] == true ? null : (res['message'] ?? 'Unable to load').toString();
      loading = false;
    });
  }

  List get _visible {
    if (query.trim().isEmpty) return people;
    final needle = query.toLowerCase();
    return people.where((p) {
      final name = '${p['display_name'] ?? ''}'.toLowerCase();
      final handle = '${p['username'] ?? ''}'.toLowerCase();
      return name.contains(needle) || handle.contains(needle);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());

    final visible = _visible;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (error != null) NoticeCard(error!, tone: VidlixTheme.danger),
          TextField(
            decoration: const InputDecoration(
              labelText: 'Search by name or handle',
              border: OutlineInputBorder(),
            ),
            onChanged: (value) => setState(() => query = value),
          ),
          const SizedBox(height: 16),
          if (visible.isEmpty)
            NoticeCard(query.trim().isEmpty ? widget.emptyMessage : 'Nobody matches that search.')
          else
            ...visible.map((p) => Card(
                  child: ListTile(
                    title: Text('${p['display_name'] ?? ''}'),
                    subtitle: Text([
                      if ('${p['username'] ?? ''}'.isNotEmpty) '@${p['username']}',
                      ...((p['categories'] as List?) ?? const []).map((c) => '${c['name'] ?? c}'),
                    ].join(' · ')),
                  ),
                )),
        ],
      ),
    );
  }
}
