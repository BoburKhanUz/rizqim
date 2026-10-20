// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_devices_uuid_push_token_request_body.g.dart';

@JsonSerializable()
class V1DevicesUuidPushTokenRequestBody {
  const V1DevicesUuidPushTokenRequestBody({required this.pushToken});

  factory V1DevicesUuidPushTokenRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1DevicesUuidPushTokenRequestBodyFromJson(json);

  @JsonKey(name: 'push_token')
  final String? pushToken;

  Map<String, Object?> toJson() =>
      _$V1DevicesUuidPushTokenRequestBodyToJson(this);
}
