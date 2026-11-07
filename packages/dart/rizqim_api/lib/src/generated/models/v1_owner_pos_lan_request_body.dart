// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_lan_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosLanRequestBody {
  const V1OwnerPosLanRequestBody({this.hubDeviceId, this.port, this.branchId});

  factory V1OwnerPosLanRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerPosLanRequestBodyFromJson(json);

  @JsonKey(name: 'hub_device_id')
  final String? hubDeviceId;
  final int? port;

  /// The branch (a branch-bound caller: their own).
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$V1OwnerPosLanRequestBodyToJson(this);
}
