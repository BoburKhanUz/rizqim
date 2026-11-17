import 'dart:async';

import 'package:rizqim_api/rizqim_api.dart' show RefreshThrottle;

/// M2 fix round 2: event reads are paced with a random spread; tests run without it (the pacing window still applies).
Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  RefreshThrottle.defaultRandom = () => 0;
  await testMain();
}
