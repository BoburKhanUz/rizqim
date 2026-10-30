// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_staff_orders_uuid_assembled_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1StaffOrdersUuidAssembledResponse
_$PostV1StaffOrdersUuidAssembledResponseFromJson(Map<String, dynamic> json) =>
    PostV1StaffOrdersUuidAssembledResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data281.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1StaffOrdersUuidAssembledResponseToJson(
  PostV1StaffOrdersUuidAssembledResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
