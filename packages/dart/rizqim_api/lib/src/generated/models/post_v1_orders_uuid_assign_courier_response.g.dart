// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_orders_uuid_assign_courier_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OrdersUuidAssignCourierResponse
_$PostV1OrdersUuidAssignCourierResponseFromJson(Map<String, dynamic> json) =>
    PostV1OrdersUuidAssignCourierResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Order.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OrdersUuidAssignCourierResponseToJson(
  PostV1OrdersUuidAssignCourierResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
