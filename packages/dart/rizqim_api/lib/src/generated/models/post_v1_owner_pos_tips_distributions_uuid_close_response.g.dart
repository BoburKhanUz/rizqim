// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_tips_distributions_uuid_close_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosTipsDistributionsUuidCloseResponse
_$PostV1OwnerPosTipsDistributionsUuidCloseResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerPosTipsDistributionsUuidCloseResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data118.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerPosTipsDistributionsUuidCloseResponseToJson(
  PostV1OwnerPosTipsDistributionsUuidCloseResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
