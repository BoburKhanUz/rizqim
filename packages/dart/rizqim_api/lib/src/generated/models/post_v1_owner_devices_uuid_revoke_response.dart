// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data33.dart';

part 'post_v1_owner_devices_uuid_revoke_response.g.dart';

@JsonSerializable()
class PostV1OwnerDevicesUuidRevokeResponse {
  const PostV1OwnerDevicesUuidRevokeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerDevicesUuidRevokeResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerDevicesUuidRevokeResponseFromJson(json);

  final bool success;
  final String message;
  final Data33 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerDevicesUuidRevokeResponseToJson(this);
}
