// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_transfer_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidTransferResponse
_$PostV1PosOrdersUuidTransferResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosOrdersUuidTransferResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data232.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosOrdersUuidTransferResponseToJson(
  PostV1PosOrdersUuidTransferResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
