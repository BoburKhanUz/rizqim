import 'dart:convert';
import 'dart:io';

/// P6-59: runs a console command of the stack's backend (`php artisan …`) with the stack's own environment, as its
/// scheduler would (the daily jobs the stack does not run on its own: the banquet payment reminders).
/// `scripts/pos-e2e.sh` sets `POS_E2E_BACKEND_DIR` and `POS_E2E_BACKEND_ENV_FILE` (one `KEY=value` per line).
Future<String> artisan(List<String> arguments) async {
  String need(String name) {
    final String? value = Platform.environment[name];
    if (value == null || value.isEmpty) {
      throw StateError(
        '$name is not set: run the tests with scripts/pos-e2e.sh',
      );
    }
    return value;
  }

  final String dir = need('POS_E2E_BACKEND_DIR');
  final Map<String, String> env = <String, String>{
    for (final String line in File(
      need('POS_E2E_BACKEND_ENV_FILE'),
    ).readAsLinesSync())
      if (line.contains('='))
        line.substring(0, line.indexOf('=')): line.substring(
          line.indexOf('=') + 1,
        ),
  };
  final ProcessResult result = await Process.run(
    'php',
    <String>['artisan', ...arguments, '--no-interaction'],
    workingDirectory: dir,
    environment: env,
    stdoutEncoding: utf8,
    stderrEncoding: utf8,
  );
  if (result.exitCode != 0) {
    throw StateError(
      'php artisan ${arguments.join(' ')} failed (${result.exitCode}): ${result.stderr}${result.stdout}',
    );
  }
  return result.stdout as String;
}
