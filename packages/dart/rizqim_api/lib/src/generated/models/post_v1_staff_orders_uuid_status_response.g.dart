// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_staff_orders_uuid_status_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1StaffOrdersUuidStatusResponse
_$PostV1StaffOrdersUuidStatusResponseFromJson(Map<String, dynamic> json) =>
    PostV1StaffOrdersUuidStatusResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data284.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1StaffOrdersUuidStatusResponseToJson(
  PostV1StaffOrdersUuidStatusResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
