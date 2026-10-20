// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'broadcasting_auth_request_body.g.dart';

@JsonSerializable()
class BroadcastingAuthRequestBody {
  const BroadcastingAuthRequestBody({
    required this.socketId,
    required this.channelName,
  });

  factory BroadcastingAuthRequestBody.fromJson(Map<String, Object?> json) =>
      _$BroadcastingAuthRequestBodyFromJson(json);

  /// Reverb ulanishining socket_id si.
  @JsonKey(name: 'socket_id')
  final String socketId;

  /// Masalan, `private-orders.store.1`.
  @JsonKey(name: 'channel_name')
  final String channelName;

  Map<String, Object?> toJson() => _$BroadcastingAuthRequestBodyToJson(this);
}
