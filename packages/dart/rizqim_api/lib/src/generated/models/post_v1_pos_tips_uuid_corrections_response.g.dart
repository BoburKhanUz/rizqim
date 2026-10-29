// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_tips_uuid_corrections_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosTipsUuidCorrectionsResponse
_$PostV1PosTipsUuidCorrectionsResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosTipsUuidCorrectionsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data250.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosTipsUuidCorrectionsResponseToJson(
  PostV1PosTipsUuidCorrectionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
