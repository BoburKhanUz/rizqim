// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_branches_id_label_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBranchesIdLabelSettingsRequestBody
_$V1OwnerBranchesIdLabelSettingsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBranchesIdLabelSettingsRequestBody(
  size: json['size'] == null
      ? null
      : LabelSize.fromJson(json['size'] as String),
  scope: json['scope'] == null
      ? null
      : LabelScope.fromJson(json['scope'] as String),
  categoryIds: (json['category_ids'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
  perUnit: json['per_unit'] as bool?,
  bagLabel: json['bag_label'] as bool?,
);

Map<String, dynamic> _$V1OwnerBranchesIdLabelSettingsRequestBodyToJson(
  V1OwnerBranchesIdLabelSettingsRequestBody instance,
) => <String, dynamic>{
  'size': instance.size,
  'scope': instance.scope,
  'category_ids': instance.categoryIds,
  'per_unit': instance.perUnit,
  'bag_label': instance.bagLabel,
};
