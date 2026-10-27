// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_orders_uuid_bills_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosOrdersUuidBillsResponse _$GetV1PosOrdersUuidBillsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosOrdersUuidBillsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data175.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosOrdersUuidBillsResponseToJson(
  GetV1PosOrdersUuidBillsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
