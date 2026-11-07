// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_lan_rotate_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosLanRotateRequestBody {
  const V1OwnerPosLanRotateRequestBody({this.branchId});

  factory V1OwnerPosLanRotateRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerPosLanRotateRequestBodyFromJson(json);

  /// The branch (a branch-bound caller: their own).
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$V1OwnerPosLanRotateRequestBodyToJson(this);
}
