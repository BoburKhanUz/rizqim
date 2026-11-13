// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_promo_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidPromoRequestBody _$V1PosOrdersUuidPromoRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidPromoRequestBody(
  version: (json['version'] as num).toInt(),
  code: json['code'] as String,
);

Map<String, dynamic> _$V1PosOrdersUuidPromoRequestBodyToJson(
  V1PosOrdersUuidPromoRequestBody instance,
) => <String, dynamic>{'version': instance.version, 'code': instance.code};
