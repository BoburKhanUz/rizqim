// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data14.dart';

part 'delete_v1_devices_uuid_push_token_response.g.dart';

@JsonSerializable()
class DeleteV1DevicesUuidPushTokenResponse {
  const DeleteV1DevicesUuidPushTokenResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1DevicesUuidPushTokenResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1DevicesUuidPushTokenResponseFromJson(json);

  final bool success;
  final String message;
  final Data14 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1DevicesUuidPushTokenResponseToJson(this);
}
