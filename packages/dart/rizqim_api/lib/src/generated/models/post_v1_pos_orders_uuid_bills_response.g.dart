// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_bills_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidBillsResponse _$PostV1PosOrdersUuidBillsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidBillsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data175.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidBillsResponseToJson(
  PostV1PosOrdersUuidBillsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
