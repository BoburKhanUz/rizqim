// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_banquets_packages_package_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosBanquetsPackagesPackageRequestBody
_$V1OwnerPosBanquetsPackagesPackageRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPosBanquetsPackagesPackageRequestBody(
  name: json['name'] as String?,
  perPersonPrice: json['per_person_price'] as num?,
  minimumGuests: (json['minimum_guests'] as num?)?.toInt(),
  included: (json['included'] as List<dynamic>?)
      ?.map((e) => e as String?)
      .toList(),
  description: json['description'] as String?,
  isActive: json['is_active'] as bool?,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
  items: (json['items'] as List<dynamic>?)
      ?.map((e) => Items8.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$V1OwnerPosBanquetsPackagesPackageRequestBodyToJson(
  V1OwnerPosBanquetsPackagesPackageRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'per_person_price': instance.perPersonPrice,
  'minimum_guests': instance.minimumGuests,
  'included': instance.included,
  'description': instance.description,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
  'items': instance.items,
};
