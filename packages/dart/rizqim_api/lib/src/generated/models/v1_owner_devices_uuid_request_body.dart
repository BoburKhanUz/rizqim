// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_devices_uuid_request_body.g.dart';

@JsonSerializable()
class V1OwnerDevicesUuidRequestBody {
  const V1OwnerDevicesUuidRequestBody({required this.name});

  factory V1OwnerDevicesUuidRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerDevicesUuidRequestBodyFromJson(json);

  final String name;

  Map<String, Object?> toJson() => _$V1OwnerDevicesUuidRequestBodyToJson(this);
}
