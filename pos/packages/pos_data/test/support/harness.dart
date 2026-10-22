import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Butun ma'lumot qatlami soxta backend ustida: [FakePosAdapter] → [RizqimClient] → [PosData].
class Harness {
  Harness({DateTime? start}) : now = start ?? DateTime.utc(2026, 10, 22, 13) {
    adapter = FakePosAdapter(clock: () => now);
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    pos = PosData(client, clock: () => now, random: Random(7));
  }

  DateTime now;
  late final FakePosAdapter adapter;
  late final RizqimClient client;
  late final PosData pos;

  FakePosState get state => adapter.state;

  /// Terminal ulangan, [userId] (standart — kassir) PIN bilan kirgan.
  Future<void> signIn({int userId = 3}) async {
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: userId, pin: state.pins[userId]!);
  }

  /// Oxirgi so'rov.
  RequestOptions get last => adapter.requests.last;

  /// So'rov tanasi (JSON) xarita sifatida.
  static Map<String, Object?> bodyOf(RequestOptions r) {
    final Object? data = r.data;
    if (data is Map) return data.cast<String, Object?>();
    if (data is String) {
      return (jsonDecode(data) as Map<Object?, Object?>)
          .cast<String, Object?>();
    }
    return <String, Object?>{};
  }
}

/// Mahalliy backend'dan (P3-20 ishida `php artisan serve` bilan choyxona oqimi) olingan haqiqiy javob.
Map<String, Object?> fixture(String name) => (jsonDecode(
  File('test/fixtures/$name.json').readAsStringSync(),
) as Map<Object?, Object?>).cast<String, Object?>();

/// Fixture'ning `data` qismi.
Object? fixtureData(String name) => fixture(name)['data'];

final RegExp uuidV4 = RegExp(
  r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
);
