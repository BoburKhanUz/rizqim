// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_bills_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidBillsRequestBody _$V1PosOrdersUuidBillsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidBillsRequestBody(
  version: (json['version'] as num).toInt(),
  mode: Mode2.fromJson(json['mode'] as String),
  parts: (json['parts'] as num?)?.toInt(),
  bills: (json['bills'] as List<dynamic>?)
      ?.map((e) => Bills3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$V1PosOrdersUuidBillsRequestBodyToJson(
  V1PosOrdersUuidBillsRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'mode': instance.mode,
  'parts': instance.parts,
  'bills': instance.bills,
};
