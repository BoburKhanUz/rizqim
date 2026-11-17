import 'dart:async';

/// M2: "the app came back to the foreground" for the Flutter-free data layers (refresh schedulers, the KDS board, the
/// customer display). The apps feed it from Flutter's lifecycle (`AppLifecycleListener(onResume: AppResume.notify)`):
/// a till, a phone or a kitchen screen that slept or was in the background re-reads at once instead of waiting for the
/// next poll. Bursts need no merging here: every listener runs one refresh at a time and folds the rest into one.
abstract final class AppResume {
  static final StreamController<void> _resumed =
      StreamController<void>.broadcast(sync: true);

  static Stream<void> get resumed => _resumed.stream;

  static void notify() => _resumed.add(null);
}
