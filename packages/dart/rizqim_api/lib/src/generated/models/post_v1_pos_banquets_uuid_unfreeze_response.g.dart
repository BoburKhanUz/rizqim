// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_unfreeze_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidUnfreezeResponse
_$PostV1PosBanquetsUuidUnfreezeResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBanquetsUuidUnfreezeResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data243.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBanquetsUuidUnfreezeResponseToJson(
  PostV1PosBanquetsUuidUnfreezeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
