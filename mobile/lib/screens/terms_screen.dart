import 'package:flutter/material.dart';

import '../api.dart';
import '../theme.dart';

/// The agreement, in full, before anyone accepts it.
///
/// The text comes from the server rather than being copied into the app: two
/// copies of an agreement drift apart, and the one on the phone would be the
/// stale one. The Accept button stays disabled until the reader reaches the
/// bottom — a tick box under an unread wall of text is not consent.
class TermsScreen extends StatefulWidget {
  const TermsScreen({super.key, required this.api, required this.role, required this.roleLabel});

  final Api api;
  final String role;
  final String roleLabel;

  @override
  State<TermsScreen> createState() => _TermsScreenState();
}

class _TermsScreenState extends State<TermsScreen> {
  final scroll = ScrollController();

  Map<String, dynamic> terms = {};
  bool loading = true;
  bool reachedEnd = false;
  String? error;

  @override
  void initState() {
    super.initState();
    scroll.addListener(_onScroll);
    _load();
  }

  @override
  void dispose() {
    scroll.removeListener(_onScroll);
    scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (reachedEnd) return;

    if (scroll.offset >= scroll.position.maxScrollExtent - 24) {
      setState(() => reachedEnd = true);
    }
  }

  Future<void> _load() async {
    final res = await widget.api.get('/auth/terms');
    if (!mounted) return;

    final roles = (Api.mapOf(res)['roles'] as Map?) ?? const {};

    setState(() {
      terms = (roles[widget.role] as Map?)?.cast<String, dynamic>() ?? {};
      error = res['success'] == true ? null : Api.firstError(res, 'The terms could not be loaded.');
      loading = false;
    });

    // A short agreement may not scroll at all, in which case it has already
    // been seen in full.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !scroll.hasClients) return;

      if (scroll.position.maxScrollExtent <= 0) {
        setState(() => reachedEnd = true);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final points = (terms['points'] as List?) ?? const [];

    return Scaffold(
      appBar: AppBar(title: Text('${widget.roleLabel} terms')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Expanded(
                  child: ListView(
                    controller: scroll,
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                    children: [
                      if (error != null) NoticeCard(error!, tone: VidlixTheme.danger),
                      if (terms['intro'] != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 18),
                          child: Text(
                            '${terms['intro']}',
                            style: const TextStyle(color: VidlixTheme.muted, height: 1.5),
                          ),
                        ),
                      ...points.map((p) {
                        final point = p as Map;

                        return Padding(
                          padding: const EdgeInsets.only(bottom: 20),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${point['title']}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 15,
                                  color: VidlixTheme.ink,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                '${point['body']}',
                                style: const TextStyle(color: VidlixTheme.muted, height: 1.5),
                              ),
                            ],
                          ),
                        );
                      }),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
                  decoration: const BoxDecoration(
                    color: VidlixTheme.surface,
                    border: Border(top: BorderSide(color: VidlixTheme.line)),
                  ),
                  child: SafeArea(
                    top: false,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (!reachedEnd)
                          const Padding(
                            padding: EdgeInsets.only(bottom: 10),
                            child: Text(
                              'Scroll to the end to accept.',
                              style: TextStyle(color: VidlixTheme.muted, fontSize: 13),
                            ),
                          ),
                        FilledButton(
                          onPressed: reachedEnd ? () => Navigator.of(context).pop(true) : null,
                          child: const Text('I have read and accept'),
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
