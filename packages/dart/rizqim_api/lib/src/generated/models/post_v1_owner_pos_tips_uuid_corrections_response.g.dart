// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_tips_uuid_corrections_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosTipsUuidCorrectionsResponse
_$PostV1OwnerPosTipsUuidCorrectionsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerPosTipsUuidCorrectionsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data122.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerPosTipsUuidCorrectionsResponseToJson(
  PostV1OwnerPosTipsUuidCorrectionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
