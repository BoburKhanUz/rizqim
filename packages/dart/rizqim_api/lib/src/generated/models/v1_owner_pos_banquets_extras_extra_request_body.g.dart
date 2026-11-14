// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_banquets_extras_extra_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosBanquetsExtrasExtraRequestBody
_$V1OwnerPosBanquetsExtrasExtraRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerPosBanquetsExtrasExtraRequestBody(
      name: json['name'] as String?,
      price: json['price'] as num?,
      pricing: json['pricing'] == null
          ? null
          : BanquetExtraPricing.fromJson(json['pricing'] as String),
      isActive: json['is_active'] as bool?,
      sortOrder: (json['sort_order'] as num?)?.toInt(),
    );

Map<String, dynamic> _$V1OwnerPosBanquetsExtrasExtraRequestBodyToJson(
  V1OwnerPosBanquetsExtrasExtraRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'price': instance.price,
  'pricing': instance.pricing,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
};
