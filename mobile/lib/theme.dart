import 'package:flutter/material.dart';

/// The same palette the website uses, so the two clients look like one product.
///
/// These values are the CSS custom properties from public/css/app.css. If one
/// changes there, change it here: a phone that is a slightly different indigo
/// from the site looks like a counterfeit of it.
class VidlixTheme {
  static const accent = Color(0xFF5B5CE2);
  static const accentSoft = Color(0xFFEEEEFD);
  static const ink = Color(0xFF14161C);
  static const muted = Color(0xFF5A6070);
  static const line = Color(0xFFE2E5EF);
  static const bg = Color(0xFFF7F8FC);
  static const surface = Color(0xFFFFFFFF);
  static const danger = Color(0xFFB4232A);

  static ThemeData build() {
    final scheme = ColorScheme.fromSeed(
      seedColor: accent,
      brightness: Brightness.light,
      primary: accent,
      surface: surface,
    );

    OutlineInputBorder border(Color color, [double width = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: color, width: width),
        );

    return ThemeData(
      colorScheme: scheme,
      scaffoldBackgroundColor: bg,
      useMaterial3: true,
      fontFamily: 'Roboto',
      appBarTheme: const AppBarTheme(
        backgroundColor: bg,
        foregroundColor: ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: ink,
          fontSize: 18,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.3,
        ),
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        margin: const EdgeInsets.only(bottom: 12),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: line),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
        border: border(line),
        enabledBorder: border(line),
        focusedBorder: border(accent, 1.6),
        errorBorder: border(danger),
        focusedErrorBorder: border(danger, 1.6),
        labelStyle: const TextStyle(color: muted),
        floatingLabelStyle: const TextStyle(color: accent),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: accent,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: ink,
          minimumSize: const Size.fromHeight(48),
          side: const BorderSide(color: line),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: accent),
      ),
      dividerColor: line,
      listTileTheme: const ListTileThemeData(textColor: ink, iconColor: muted),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        indicatorColor: accentSoft,
        elevation: 0,
        labelTextStyle: WidgetStateProperty.all(
          const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: ink,
        contentTextStyle: const TextStyle(color: Colors.white),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
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
    final bad = tone == VidlixTheme.danger;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: bad ? const Color(0xFFFDF2F2) : VidlixTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: bad ? const Color(0xFFF0D2D2) : VidlixTheme.line),
      ),
      child: Text(message, style: TextStyle(color: tone, height: 1.45)),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 18, bottom: 10),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w700,
          color: VidlixTheme.ink,
          letterSpacing: -0.2,
        ),
      ),
    );
  }
}

/// A password field with a reveal button.
///
/// Typing a long password blind on a phone keyboard is how people end up
/// locked out of an account they just created, so every password field in the
/// app offers to show what was typed.
class PasswordField extends StatefulWidget {
  const PasswordField({
    super.key,
    required this.controller,
    required this.label,
    this.textInputAction,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final TextInputAction? textInputAction;
  final VoidCallback? onSubmitted;

  @override
  State<PasswordField> createState() => _PasswordFieldState();
}

class _PasswordFieldState extends State<PasswordField> {
  bool hidden = true;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: widget.controller,
      obscureText: hidden,
      autocorrect: false,
      enableSuggestions: false,
      textInputAction: widget.textInputAction,
      onSubmitted: widget.onSubmitted == null ? null : (_) => widget.onSubmitted!(),
      decoration: InputDecoration(
        labelText: widget.label,
        suffixIcon: IconButton(
          onPressed: () => setState(() => hidden = !hidden),
          icon: Icon(hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined),
          tooltip: hidden ? 'Show password' : 'Hide password',
          color: VidlixTheme.muted,
        ),
      ),
    );
  }
}
