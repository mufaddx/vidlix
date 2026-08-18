import 'package:flutter/material.dart';

import '../api.dart';
import 'account_screen.dart';
import 'campaigns_screen.dart';
import 'earnings_screen.dart';
import 'home_screen.dart';
import 'inbox_screen.dart';
import 'projects_screen.dart';

class ShellScreen extends StatefulWidget {
  const ShellScreen({super.key, required this.api, required this.onSignOut});

  final Api api;
  final VoidCallback onSignOut;

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen> {
  int index = 0;

  static const _titles = ['Vidlix', 'Campaigns', 'Projects', 'Earnings', 'Messages', 'Account'];

  @override
  Widget build(BuildContext context) {
    final pages = [
      HomeScreen(api: widget.api),
      CampaignsScreen(api: widget.api),
      ProjectsScreen(api: widget.api),
      EarningsScreen(api: widget.api),
      InboxScreen(api: widget.api),
      AccountScreen(api: widget.api, onSignOut: widget.onSignOut),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(_titles[index])),
      body: IndexedStack(index: index, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (i) => setState(() => index = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.campaign_outlined), label: 'Campaigns'),
          NavigationDestination(icon: Icon(Icons.movie_outlined), label: 'Projects'),
          NavigationDestination(icon: Icon(Icons.account_balance_wallet_outlined), label: 'Earnings'),
          NavigationDestination(icon: Icon(Icons.chat_bubble_outline), label: 'Messages'),
          NavigationDestination(icon: Icon(Icons.person_outline), label: 'Account'),
        ],
      ),
    );
  }
}
