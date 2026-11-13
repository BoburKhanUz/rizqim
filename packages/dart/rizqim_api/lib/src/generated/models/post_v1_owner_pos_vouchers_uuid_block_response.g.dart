// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_vouchers_uuid_block_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosVouchersUuidBlockResponse
_$PostV1OwnerPosVouchersUuidBlockResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerPosVouchersUuidBlockResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data147.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerPosVouchersUuidBlockResponseToJson(
  PostV1OwnerPosVouchersUuidBlockResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
