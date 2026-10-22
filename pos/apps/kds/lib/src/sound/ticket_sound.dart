import 'dart:async';

import 'package:audioplayers/audioplayers.dart';

/// Yangi chipta ovozi.
abstract interface class TicketSound {
  Future<void> play();

  /// Qator o'chirildi ("O'CHIRILDI"): yangi chiptadan farqli, pastga tushuvchi signal.
  Future<void> playVoid();

  Future<void> dispose();
}

/// Ilova ichidagi `assets/sounds/new_ticket.wav` (`audioplayers`: Android, Windows; Linux'da GStreamer). Ketma-ket kelgan
/// bir nechta chipta uchun bir marta chalinadi ([minGap]).
class AssetTicketSound implements TicketSound {
  AssetTicketSound({this.minGap = const Duration(seconds: 2)});

  static const String asset = 'sounds/new_ticket.wav';
  static const String voidAsset = 'sounds/void.wav';

  final Duration minGap;
  AudioPlayer? _player;
  DateTime? _last;

  @override
  Future<void> play() => _play(asset);

  @override
  Future<void> playVoid() => _play(voidAsset);

  Future<void> _play(String asset) async {
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

/// Ovozsiz (testlar, audio yo'q muhit).
class SilentTicketSound implements TicketSound {
  int plays = 0;
  int voidPlays = 0;

  @override
  Future<void> play() async => plays++;

  @override
  Future<void> playVoid() async => voidPlays++;

  @override
  Future<void> dispose() async {}
}
