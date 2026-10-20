// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_auth_refresh_request_body.g.dart';

@JsonSerializable()
class V1AuthRefreshRequestBody {
  const V1AuthRefreshRequestBody({
    required this.refreshToken,
    required this.deviceUuid,
  });

  factory V1AuthRefreshRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AuthRefreshRequestBodyFromJson(json);

  @JsonKey(name: 'refresh_token')
  final String refreshToken;
  @JsonKey(name: 'device_uuid')
  final String deviceUuid;

  Map<String, Object?> toJson() => _$V1AuthRefreshRequestBodyToJson(this);
}
