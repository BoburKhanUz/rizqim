// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_devices_pair_request_body.g.dart';

@JsonSerializable()
class V1DevicesPairRequestBody {
  const V1DevicesPairRequestBody({
    required this.code,
    this.name,
    this.platform,
    this.appVersion,
  });

  factory V1DevicesPairRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1DevicesPairRequestBodyFromJson(json);

  final String code;
  final String? name;
  final String? platform;
  @JsonKey(name: 'app_version')
  final String? appVersion;

  Map<String, Object?> toJson() => _$V1DevicesPairRequestBodyToJson(this);
}
