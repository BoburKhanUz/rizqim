// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_banquets_extras_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosBanquetsExtrasRequestBody
_$V1OwnerPosBanquetsExtrasRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerPosBanquetsExtrasRequestBody(
      name: json['name'] as String,
      price: json['price'] as num,
      pricing: BanquetExtraPricing.fromJson(json['pricing'] as String),
      isActive: json['is_active'] as bool?,
      sortOrder: (json['sort_order'] as num?)?.toInt(),
    );

Map<String, dynamic> _$V1OwnerPosBanquetsExtrasRequestBodyToJson(
  V1OwnerPosBanquetsExtrasRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'price': instance.price,
  'pricing': instance.pricing,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
};
