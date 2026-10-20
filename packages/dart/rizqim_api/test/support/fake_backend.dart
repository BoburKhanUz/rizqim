import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';

typedef Handler = FutureOr<ResponseBody> Function(RequestOptions options);

/// A [HttpClientAdapter] that answers from a function instead of the network.
class FakeAdapter implements HttpClientAdapter {
  FakeAdapter(this.handler);

  Handler handler;
  final List<RequestOptions> requests = <RequestOptions>[];

  int count(String path) =>
      requests.where((RequestOptions r) => r.uri.path == path).length;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    return handler(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody json(
  int status,
  Object? body, {
  Map<String, List<String>> headers = const <String, List<String>>{},
}) => ResponseBody.fromString(
  jsonEncode(body),
  status,
  headers: <String, List<String>>{
    Headers.contentTypeHeader: <String>[Headers.jsonContentType],
    ...headers,
  },
);

ResponseBody ok(Object? data, {int status = 200}) => json(
  status,
  <String, Object?>{'success': true, 'message': 'OK', 'data': data},
);

ResponseBody refuse(
  int status,
  String message, {
  String? reason,
  Map<String, List<String>> headers = const <String, List<String>>{},
}) => json(status, <String, Object?>{
  'success': false,
  'message': message,
  'data': <String, Object?>{'reason': ?reason},
}, headers: headers);

Map<String, Object?> deviceJson({
  String uuid = '11111111-1111-4111-8111-111111111111',
  String type = 'kds',
}) => <String, Object?>{
  'uuid': uuid,
  'type': type,
  'name': 'Oshxona',
  'platform': 'linux',
  'app_version': '0.1.0',
  'store_id': 7,
  'branch_id': 3,
  'user': null,
  'has_push_token': false,
  'last_seen_at': null,
  'revoked_at': null,
  'created_at': null,
};

Map<String, Object?> pairData({String token = 'terminal-token'}) =>
    <String, Object?>{
      'token': token,
      'device': deviceJson(),
      'store': <String, Object?>{'id': 7, 'name': 'Rizqim Cafe'},
      'branch': <String, Object?>{'id': 3, 'name': 'Chilonzor'},
    };

Map<String, Object?> tokenData({
  required String access,
  required String refresh,
  required DateTime accessExpires,
  DateTime? refreshExpires,
  String deviceUuid = '22222222-2222-4222-8222-222222222222',
}) => <String, Object?>{
  'token': access,
  'token_expires_at': accessExpires.toUtc().toIso8601String(),
  'refresh_token': refresh,
  'refresh_token_expires_at':
      (refreshExpires ?? accessExpires.add(const Duration(days: 30)))
          .toUtc()
          .toIso8601String(),
  'device': deviceJson(uuid: deviceUuid, type: 'courier'),
};

Map<String, Object?> staffData({
  String token = 'staff-token',
  required DateTime expiresAt,
}) => <String, Object?>{
  'token': token,
  'expires_at': expiresAt.toUtc().toIso8601String(),
  'user': <String, Object?>{
    'id': 5,
    'name': 'Aziz',
    'role': 'cook',
    'branch_id': 3,
  },
  'permissions': <String>['orders.view', 'kitchen.manage'],
};
