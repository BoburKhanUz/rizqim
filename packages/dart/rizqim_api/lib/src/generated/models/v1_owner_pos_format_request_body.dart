// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_format_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosFormatRequestBody {
  const V1OwnerPosFormatRequestBody({
    this.modules,
    this.branchId,
    this.profile,
    this.mode,
  });

  factory V1OwnerPosFormatRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerPosFormatRequestBodyFromJson(json);

  /// Module switches over the profile: {module: true|false|null}.
  final Map<String, bool?>? modules;

  /// The branch (a branch-bound caller: their own when omitted).
  @JsonKey(name: 'branch_id')
  final int? branchId;

  /// The format profile.
  final String? profile;

  /// counter, table or hybrid.
  final String? mode;

  Map<String, Object?> toJson() => _$V1OwnerPosFormatRequestBodyToJson(this);
}
