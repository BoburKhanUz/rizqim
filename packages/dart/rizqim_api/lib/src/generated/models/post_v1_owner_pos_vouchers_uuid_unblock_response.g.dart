// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_vouchers_uuid_unblock_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosVouchersUuidUnblockResponse
_$PostV1OwnerPosVouchersUuidUnblockResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerPosVouchersUuidUnblockResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data148.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerPosVouchersUuidUnblockResponseToJson(
  PostV1OwnerPosVouchersUuidUnblockResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
