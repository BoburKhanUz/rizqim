import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_kds/src/app.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'fake_kds.dart';

/// Dio taymerlari faqat soxta soat surilganda ishlaydi.
Future<T> settle<T>(WidgetTester tester, Future<T> future) async {
  await tester.pumpAndSettle();
  return future;
}

void setScreen(WidgetTester tester, Size size) {
  tester.view
    ..physicalSize = size
    ..devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

/// Ilovani ochadi (planshet, landshaft).
Future<void> pumpKds(
  WidgetTester tester,
  KdsHarness h, {
  Size size = const Size(1280, 800),
}) async {
  setScreen(tester, size);
  await h.client.auth.restore();
  await tester.pumpWidget(KdsApp(services: h.services));
  await tester.pumpAndSettle();
}

/// Ulangan terminal, PIN ekrani ochiq.
Future<void> pumpPaired(
  WidgetTester tester,
  KdsHarness h, {
  Size size = const Size(1280, 800),
}) async {
  await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
  await pumpKds(tester, h, size: size);
}

/// PIN klaviaturasida terish.
Future<void> typePin(
  WidgetTester tester,
  String pin, {
  bool submit = true,
}) async {
  for (final String d in pin.split('')) {
    await tester.tap(find.bySemanticsLabel(d).first);
    await tester.pump();
  }
  if (submit) {
    await tester.tap(find.bySemanticsLabel('Kirish'));
  }
  await tester.pumpAndSettle();
}

/// Ulangan, oshpaz Aziz PIN bilan kirgan, doska ochiq.
Future<void> pumpBoard(
  WidgetTester tester,
  KdsHarness h, {
  Size size = const Size(1280, 800),
}) async {
  await pumpPaired(tester, h, size: size);
  await tester.tap(find.text('Aziz Karimov'));
  await tester.pumpAndSettle();
  await typePin(tester, h.state.pin);
}

/// Soatni surib, ekranni yangilaydi (taymer har soniyada).
Future<void> advance(WidgetTester tester, KdsHarness h, Duration d) async {
  var left = d;
  while (left > Duration.zero) {
    final Duration step = left > const Duration(seconds: 1)
        ? const Duration(seconds: 1)
        : left;
    h.now = h.now.add(step);
    await tester.pump(step);
    left -= step;
  }
  await tester.pumpAndSettle();
}

Finder buttonLabeled(String label) => find.widgetWithText(RzButton, label);
