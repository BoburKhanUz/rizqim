// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_orders_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosOrdersUuidResponse _$GetV1PosOrdersUuidResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosOrdersUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data207.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosOrdersUuidResponseToJson(
  GetV1PosOrdersUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
