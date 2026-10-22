import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';

import 'fake_pos_state.dart';

/// Dio adapteri: so'rovlarga [FakePosState] javob beradi (`RizqimClient(dio: Dio()..httpClientAdapter = adapter)`).
class FakePosAdapter implements HttpClientAdapter {
  FakePosAdapter({FakePosState? state, DateTime Function()? clock})
    : state = state ?? FakePosState(clock: clock);

  final FakePosState state;

  /// Hamma kelgan so'rovlar (sarlavhalar va tana bilan).
  final List<RequestOptions> requests = <RequestOptions>[];

  /// Hamma so'rov tarmoq xatosi bilan tugaydi.
  bool offline = false;

  /// Berilsa va `null` qaytarmasa — shu javob ([state] chaqirilmaydi).
  FakeResponse? Function(RequestOptions options)? respond;

  /// Berilsa, o'zgartiruvchi so'rovlar shu tugaguncha javob kutadi (yuklanish holatini ko'rish uchun).
  Completer<void>? hold;

  Iterable<RequestOptions> to(String method, Pattern path) => requests.where(
    (RequestOptions r) => r.method == method && r.uri.path.contains(path),
  );

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    if (offline) {
      throw DioException.connectionError(
        requestOptions: options,
        reason: 'offline',
      );
    }
    FakeResponse? response = respond?.call(options);
    final Completer<void>? gate = hold;
    if (response == null && gate != null && options.method != 'GET') {
      await gate.future;
    }
    final String? auth = options.headers['Authorization'] as String?;
    response ??= state.handle(
      options.method,
      options.uri.path,
      options.uri.queryParameters,
      options.data,
      auth?.replaceFirst('Bearer ', ''),
      idempotencyKey: options.headers['Idempotency-Key'] as String?,
    );
    return ResponseBody.fromString(
      jsonEncode(response.body),
      response.status,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
        for (final MapEntry<String, String> h in response.headers.entries)
          h.key.toLowerCase(): <String>[h.value],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
