// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_staff_orders_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1StaffOrdersUuidResponse _$GetV1StaffOrdersUuidResponseFromJson(
  Map<String, dynamic> json,
) => GetV1StaffOrdersUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data260.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1StaffOrdersUuidResponseToJson(
  GetV1StaffOrdersUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
