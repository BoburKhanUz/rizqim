// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_request_bill_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidRequestBillResponse
_$PostV1PosOrdersUuidRequestBillResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosOrdersUuidRequestBillResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data168.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosOrdersUuidRequestBillResponseToJson(
  PostV1PosOrdersUuidRequestBillResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
