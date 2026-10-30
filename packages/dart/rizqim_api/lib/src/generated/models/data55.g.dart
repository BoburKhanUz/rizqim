// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data55.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data55 _$Data55FromJson(Map<String, dynamic> json) => Data55(
  branchId: (json['branch_id'] as num).toInt(),
  configured: json['configured'] as bool,
  size: json['size'] as String,
  sizeLabel: json['size_label'] as String,
  widthMm: (json['width_mm'] as num).toInt(),
  heightMm: (json['height_mm'] as num).toInt(),
  scope: json['scope'] as String,
  scopeLabel: json['scope_label'] as String,
  categoryIds: (json['category_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  perUnit: json['per_unit'] as bool,
  bagLabel: json['bag_label'] as bool,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data55ToJson(Data55 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'configured': instance.configured,
  'size': instance.size,
  'size_label': instance.sizeLabel,
  'width_mm': instance.widthMm,
  'height_mm': instance.heightMm,
  'scope': instance.scope,
  'scope_label': instance.scopeLabel,
  'category_ids': instance.categoryIds,
  'per_unit': instance.perUnit,
  'bag_label': instance.bagLabel,
  'updated_at': instance.updatedAt,
};
