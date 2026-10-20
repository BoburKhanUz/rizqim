// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_promo_validate_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PromoValidateRequestBody _$V1PromoValidateRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PromoValidateRequestBody(
  code: json['code'] as String,
  storeId: (json['store_id'] as num).toInt(),
  subtotal: json['subtotal'] as num?,
);

Map<String, dynamic> _$V1PromoValidateRequestBodyToJson(
  V1PromoValidateRequestBody instance,
) => <String, dynamic>{
  'code': instance.code,
  'store_id': instance.storeId,
  'subtotal': instance.subtotal,
};
