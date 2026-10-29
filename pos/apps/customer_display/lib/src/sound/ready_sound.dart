import 'dart:async';

import 'package:audioplayers/audioplayers.dart';

/// Tayyor raqam ovozi ("ding-dong").
abstract interface class ReadySound {
  Future<void> play();

  Future<void> dispose();
}

/// Ilova ichidagi `assets/sounds/ready.wav` (`audioplayers`: Android, Windows; Linux'da GStreamer). Ketma-ket tayyor
/// bo'lgan raqamlar uchun [minGap] ichida bir marta chalinadi.
class AssetReadySound implements ReadySound {
  AssetReadySound({this.minGap = const Duration(milliseconds: 1200)});

  static const String asset = 'sounds/ready.wav';

  final Duration minGap;
  AudioPlayer? _player;
  DateTime? _last;

  @override
  Future<void> play() async {
    final DateTime now = DateTime.now();
    final DateTime? last = _last;
    if (last != null && now.difference(last) < minGap) return;
    _last = now;
    try {
      AudioPlayer? player = _player;
      if (player == null) {
        player = _player = AudioPlayer();
        await player.setReleaseMode(ReleaseMode.stop);
      }
      await player.stop();
      await player.play(AssetSource(asset));
    } on Object {
      // Ovoz chiqmasa ham ekran ishlayveradi (masalan, qurilmada audio yo'q)
    }
  }

  @override
  Future<void> dispose() async {
    final AudioPlayer? player = _player;
    _player = null;
    await player?.dispose();
  }
}

/// Ovozsiz (testlar, audio yo'q muhit): necha marta chalinishi sanaladi.
class SilentReadySound implements ReadySound {
  int plays = 0;

  @override
  Future<void> play() async => plays++;

  @override
  Future<void> dispose() async {}
}
