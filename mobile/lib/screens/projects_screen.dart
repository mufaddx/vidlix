import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

class ProjectsScreen extends StatefulWidget {
  const ProjectsScreen({super.key, required this.api});

  final Api api;

  @override
  State<ProjectsScreen> createState() => _ProjectsScreenState();
}

class _ProjectsScreenState extends State<ProjectsScreen> {
  List projects = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final res = await widget.api.get('/projects', auth: true);
    if (!mounted) return;
    setState(() {
      projects = Api.listOf(res);
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
          if (projects.isEmpty)
            const NoticeCard('No projects yet. A project starts once a proposal is accepted.')
          else
            ...projects.map((p) => Card(
                  child: ListTile(
                    title: Text('${p['name'] ?? ''}'),
                    subtitle: Text('${p['status'] ?? ''} · ${Api.money(p['total_amount_minor'])}'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => ProjectDetailScreen(api: widget.api, projectId: p['id'] as int),
                    )),
                  ),
                )),
        ],
      ),
    );
  }
}

class ProjectDetailScreen extends StatefulWidget {
  const ProjectDetailScreen({super.key, required this.api, required this.projectId});

  final Api api;
  final int projectId;

  @override
  State<ProjectDetailScreen> createState() => _ProjectDetailScreenState();
}

class _ProjectDetailScreenState extends State<ProjectDetailScreen> {
  Map<String, dynamic> data = {};
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final res = await widget.api.get('/projects/${widget.projectId}', auth: true);
    if (!mounted) return;
    setState(() {
      data = Api.mapOf(res);
      loading = false;
    });
  }

  /// Payment state is whatever the server says. Opening a checkout link, or
  /// coming back from one, never changes it here.
  String _paymentLine(Map payment) {
    final status = '${payment['status']}';
    final amount = Api.money(payment['amount_minor'], '${payment['currency'] ?? 'INR'}');
    return switch (status) {
      'captured' || 'settled' => '$amount confirmed by the provider',
      'awaiting_provider' => '$amount waiting on payment provider setup',
      'failed' => '$amount failed at the provider',
      _ => '$amount not yet confirmed',
    };
  }

  @override
  Widget build(BuildContext context) {
    final project = data['project'] as Map? ?? {};
    final files = (data['files'] as List?) ?? const [];
    final payments = (data['payments'] as List?) ?? const [];

    return Scaffold(
      appBar: AppBar(title: Text('${project['name'] ?? 'Project'}')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Text('Status: ${project['status'] ?? ''}'),
                  Text('Total: ${Api.money(project['total_amount_minor'])}'),
                  if (project['deadline'] != null) Text('Deadline: ${project['deadline']}'),
                  const SectionTitle('Payments'),
                  if (payments.isEmpty)
                    const NoticeCard('No payment has been requested for this project.')
                  else
                    ...payments.map((p) => Card(
                          child: ListTile(
                            title: Text(_paymentLine(p as Map)),
                            subtitle: Text('${p['payment_uuid']}'),
                          ),
                        )),
                  const SectionTitle('Files'),
                  if (files.isEmpty)
                    const NoticeCard('No files uploaded yet. Media is stored in object storage, not in the database.')
                  else
                    ...files.map((f) => Card(
                          child: ListTile(
                            title: Text('${f['original_name']}'),
                            subtitle: Text('${f['kind']} · ${f['mime']} · ${f['disk']}'),
                          ),
                        )),
                ],
              ),
            ),
    );
  }
}
