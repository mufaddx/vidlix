import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';
import 'theme.dart';

/// Asks the server whether this build is current, and offers the new one.
///
/// Vidlix is not on Play Store, so nothing updates the app on its own and
/// installing a hand-passed APK is not a release process. The check is quiet:
/// if the server cannot be reached, or says nothing is newer, the person sees
/// nothing at all.
class AppUpdate {
  const AppUpdate(this.api);

  final Api api;

  Future<void> promptIfOutdated(BuildContext context) async {
    final info = await PackageInfo.fromPlatform();
    final res = await api.get('/app/android?version=${info.version}');

    if (res['success'] != true) {
      // Offline, or an older server that does not answer this. Either way it
      // is not worth interrupting somebody over.
      return;
    }

    final data = Api.mapOf(res);

    if (data['update_available'] != true) {
      return;
    }

    if (!context.mounted) return;

    final required = data['update_required'] == true;

    await showDialog<void>(
      context: context,
      // A build that can no longer talk to the API correctly is not something
      // to dismiss; anything else is.
      barrierDismissible: !required,
      builder: (context) => PopScope(
        canPop: !required,
        child: AlertDialog(
          title: Text(required ? 'Update needed' : 'Update available'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                required
                    ? 'This version of Vidlix is too old to work properly. Version ${data['latest']} is ready.'
                    : 'Vidlix ${data['latest']} is available. You have ${info.version}.',
                style: const TextStyle(height: 1.45),
              ),
              if ((data['notes'] as String?)?.isNotEmpty ?? false) ...[
                const SizedBox(height: 12),
                Text(
                  '${data['notes']}',
                  style: const TextStyle(color: VidlixTheme.muted, height: 1.45),
                ),
              ],
              if (data['size_bytes'] is int) ...[
                const SizedBox(height: 12),
                Text(
                  '${((data['size_bytes'] as int) / 1048576).toStringAsFixed(0)} MB download',
                  style: const TextStyle(color: VidlixTheme.muted, fontSize: 12.5),
                ),
              ],
            ],
          ),
          actions: [
            if (!required)
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Later'),
              ),
            FilledButton(
              onPressed: () async {
                final url = Uri.parse('${data['download_url']}');
                // Handed to the system, which downloads it and runs Android's
                // own installer. The app cannot install a package itself.
                await launchUrl(url, mode: LaunchMode.externalApplication);
              },
              child: const Text('Update'),
            ),
          ],
        ),
      ),
    );
  }
}
