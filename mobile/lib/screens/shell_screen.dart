import 'package:flutter/material.dart';

import '../api.dart';
import 'account_screen.dart';
import 'directory_screen.dart';
import 'inbox_screen.dart';
import 'projects_screen.dart';

/// The five tabs the product is specified to have: Creator, Editor, Inbox,
/// Projects, Profile.
///
/// Campaigns and Earnings used to sit here too. They are not gone — Profile
/// opens them — because six destinations crowd a phone's bottom bar and the
/// spec names five.
class ShellScreen extends StatefulWidget {
  const ShellScreen({super.key, required this.api, required this.onSignOut});

  final Api api;
  final VoidCallback onSignOut;

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen> {
  int index = 0;

  static const _titles = ['Creators', 'Editors', 'Inbox', 'Projects', 'Profile'];

  @override
  Widget build(BuildContext context) {
    final pages = [
      DirectoryScreen(
        api: widget.api,
        path: '/creators',
        emptyMessage: 'No published creators yet.',
      ),
      DirectoryScreen(
        api: widget.api,
        path: '/editors',
        emptyMessage: 'No approved editors yet.',
      ),
      InboxScreen(api: widget.api),
      ProjectsScreen(api: widget.api),
      AccountScreen(api: widget.api, onSignOut: widget.onSignOut),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(_titles[index])),
      body: IndexedStack(index: index, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (i) => setState(() => index = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.star_outline), label: 'Creator'),
          NavigationDestination(icon: Icon(Icons.movie_outlined), label: 'Editor'),
          NavigationDestination(icon: Icon(Icons.chat_bubble_outline), label: 'Inbox'),
          NavigationDestination(icon: Icon(Icons.folder_outlined), label: 'Projects'),
          NavigationDestination(icon: Icon(Icons.person_outline), label: 'Profile'),
        ],
      ),
    );
  }
}
