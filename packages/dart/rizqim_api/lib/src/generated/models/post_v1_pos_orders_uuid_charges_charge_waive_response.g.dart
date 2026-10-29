// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_charges_charge_waive_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidChargesChargeWaiveResponse
_$PostV1PosOrdersUuidChargesChargeWaiveResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidChargesChargeWaiveResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data179.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidChargesChargeWaiveResponseToJson(
  PostV1PosOrdersUuidChargesChargeWaiveResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
