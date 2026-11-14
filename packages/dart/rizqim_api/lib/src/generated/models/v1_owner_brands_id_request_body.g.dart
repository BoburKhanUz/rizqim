// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_brands_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBrandsIdRequestBody _$V1OwnerBrandsIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBrandsIdRequestBody(
  name: json['name'] as String?,
  shortCode: json['short_code'] as String?,
  color: json['color'] as String?,
  logoUrl: json['logo_url'] as String?,
  isActive: json['is_active'] as bool?,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerBrandsIdRequestBodyToJson(
  V1OwnerBrandsIdRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'short_code': instance.shortCode,
  'color': instance.color,
  'logo_url': instance.logoUrl,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
};
