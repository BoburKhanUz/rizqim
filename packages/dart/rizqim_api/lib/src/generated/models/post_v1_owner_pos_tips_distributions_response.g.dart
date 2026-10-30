// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_tips_distributions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosTipsDistributionsResponse
_$PostV1OwnerPosTipsDistributionsResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerPosTipsDistributionsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data132.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerPosTipsDistributionsResponseToJson(
  PostV1OwnerPosTipsDistributionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
