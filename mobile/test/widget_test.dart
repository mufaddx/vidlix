import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:vidlix_mobile/main.dart';

void main() {
  testWidgets('app boots', (tester) async {
    await tester.pumpWidget(const VidlixApp());
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });
}
