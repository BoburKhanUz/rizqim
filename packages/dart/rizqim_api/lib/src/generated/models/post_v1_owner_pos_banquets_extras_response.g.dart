// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_banquets_extras_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosBanquetsExtrasResponse
_$PostV1OwnerPosBanquetsExtrasResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerPosBanquetsExtrasResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data133.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerPosBanquetsExtrasResponseToJson(
  PostV1OwnerPosBanquetsExtrasResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
