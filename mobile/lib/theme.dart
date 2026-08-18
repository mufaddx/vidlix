import 'package:flutter/material.dart';

/// The cream/ink palette the website uses, so the two clients look like one
/// product.
class VidlixTheme {
  static const ink = Color(0xFF1C1915);
  static const cream = Color(0xFFF6F3EE);
  static const accent = Color(0xFF8C4A2F);
  static const muted = Color(0xFF6B6259);
  static const line = Color(0xFFE2DACE);
  static const danger = Color(0xFF9B2C2C);

  static ThemeData build() {
    final scheme = ColorScheme.fromSeed(
      seedColor: accent,
      brightness: Brightness.light,
      surface: cream,
    );

    return ThemeData(
      colorScheme: scheme,
      scaffoldBackgroundColor: cream,
      useMaterial3: true,
      appBarTheme: const AppBarTheme(
        backgroundColor: cream,
        foregroundColor: ink,
        elevation: 0,
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: const EdgeInsets.only(bottom: 12),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: line),
        ),
      ),
      dividerColor: line,
      listTileTheme: const ListTileThemeData(textColor: ink, iconColor: muted),
    );
  }
}

/// A short, plain statement of why something is unavailable. Used wherever a
/// provider is not configured, so the app states the reason instead of showing
/// an empty screen that looks like zero.
class NoticeCard extends StatelessWidget {
  const NoticeCard(this.message, {super.key, this.tone = VidlixTheme.muted});

  final String message;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: VidlixTheme.line),
      ),
      child: Text(message, style: TextStyle(color: tone, height: 1.4)),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8, bottom: 10),
      child: Text(text, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
    );
  }
}
