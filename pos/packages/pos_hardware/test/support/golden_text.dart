import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Text snapshot ("golden") check: compares [actual] with `test/goldens/<name>.txt`. Run with `UPDATE_GOLDENS=1`
/// to (re)write the files after an intended change, then review the diff.
void expectGoldenText(String actual, String name) {
  final File file = File('test/goldens/$name.txt');
  if (Platform.environment['UPDATE_GOLDENS'] == '1' || !file.existsSync()) {
    if (Platform.environment['CI'] == 'true' && !file.existsSync()) {
      fail(
        'Missing golden test/goldens/$name.txt (run UPDATE_GOLDENS=1 flutter test locally)',
      );
    }
    file
      ..createSync(recursive: true)
      ..writeAsStringSync(actual);
    return;
  }
  expect(
    actual,
    file.readAsStringSync(),
    reason: 'golden $name differs (UPDATE_GOLDENS=1 to accept)',
  );
}
