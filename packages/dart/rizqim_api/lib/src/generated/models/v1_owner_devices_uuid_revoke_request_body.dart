// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_devices_uuid_revoke_request_body.g.dart';

@JsonSerializable()
class V1OwnerDevicesUuidRevokeRequestBody {
  const V1OwnerDevicesUuidRevokeRequestBody({this.confirmPending});

  factory V1OwnerDevicesUuidRevokeRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerDevicesUuidRevokeRequestBodyFromJson(json);

  @JsonKey(name: 'confirm_pending')
  final bool? confirmPending;

  Map<String, Object?> toJson() =>
      _$V1OwnerDevicesUuidRevokeRequestBodyToJson(this);
}
