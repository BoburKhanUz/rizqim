// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_format_template_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosFormatTemplateResponse
_$PostV1OwnerPosFormatTemplateResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerPosFormatTemplateResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data112.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerPosFormatTemplateResponseToJson(
  PostV1OwnerPosFormatTemplateResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
