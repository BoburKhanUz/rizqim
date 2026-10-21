// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_charges_charge_restore_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidChargesChargeRestoreResponse
_$PostV1PosOrdersUuidChargesChargeRestoreResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidChargesChargeRestoreResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data150.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidChargesChargeRestoreResponseToJson(
  PostV1PosOrdersUuidChargesChargeRestoreResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
