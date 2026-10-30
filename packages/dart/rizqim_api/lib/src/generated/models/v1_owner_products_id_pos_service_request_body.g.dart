// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_products_id_pos_service_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerProductsIdPosServiceRequestBody
_$V1OwnerProductsIdPosServiceRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerProductsIdPosServiceRequestBody(
      quickMultipliers: (json['quick_multipliers'] as List<dynamic>?)
          ?.map((e) => (e as num).toInt())
          .toList(),
      waitMinutes: (json['wait_minutes'] as num?)?.toInt(),
      weightMode: json['weight_mode'] == null
          ? null
          : WeightBasis.fromJson(json['weight_mode'] as String),
    );

Map<String, dynamic> _$V1OwnerProductsIdPosServiceRequestBodyToJson(
  V1OwnerProductsIdPosServiceRequestBody instance,
) => <String, dynamic>{
  'quick_multipliers': instance.quickMultipliers,
  'wait_minutes': instance.waitMinutes,
  'weight_mode': instance.weightMode,
};
