// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data12.dart';

part 'put_v1_devices_uuid_push_token_response.g.dart';

@JsonSerializable()
class PutV1DevicesUuidPushTokenResponse {
  const PutV1DevicesUuidPushTokenResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1DevicesUuidPushTokenResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1DevicesUuidPushTokenResponseFromJson(json);

  final bool success;
  final String message;
  final Data12 data;

  Map<String, Object?> toJson() =>
      _$PutV1DevicesUuidPushTokenResponseToJson(this);
}
