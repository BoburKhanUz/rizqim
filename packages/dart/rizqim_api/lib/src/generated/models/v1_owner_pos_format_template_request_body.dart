// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_format_template_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosFormatTemplateRequestBody {
  const V1OwnerPosFormatTemplateRequestBody({
    this.branchId,
    this.profile,
    this.stations,
    this.menu,
  });

  factory V1OwnerPosFormatTemplateRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosFormatTemplateRequestBodyFromJson(json);

  /// The branch (a branch-bound caller: their own when omitted).
  @JsonKey(name: 'branch_id')
  final int? branchId;

  /// The profile whose template to apply (the branch's when omitted).
  final String? profile;

  /// Add the sample kitchen stations.
  final bool? stations;

  /// Add the sample dishes (switched off, 0 price).
  final bool? menu;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosFormatTemplateRequestBodyToJson(this);
}
