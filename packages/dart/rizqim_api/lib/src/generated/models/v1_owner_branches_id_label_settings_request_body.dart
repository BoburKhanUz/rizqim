// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'label_size.dart';
import 'label_scope.dart';

part 'v1_owner_branches_id_label_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerBranchesIdLabelSettingsRequestBody {
  const V1OwnerBranchesIdLabelSettingsRequestBody({
    this.size,
    this.scope,
    this.categoryIds,
    this.perUnit,
    this.bagLabel,
  });

  factory V1OwnerBranchesIdLabelSettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBranchesIdLabelSettingsRequestBodyFromJson(json);

  final LabelSize? size;
  final LabelScope? scope;
  @JsonKey(name: 'category_ids')
  final List<int>? categoryIds;
  @JsonKey(name: 'per_unit')
  final bool? perUnit;
  @JsonKey(name: 'bag_label')
  final bool? bagLabel;

  Map<String, Object?> toJson() =>
      _$V1OwnerBranchesIdLabelSettingsRequestBodyToJson(this);
}
