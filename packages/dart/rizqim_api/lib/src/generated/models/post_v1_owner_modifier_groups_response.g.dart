// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_modifier_groups_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerModifierGroupsResponse _$PostV1OwnerModifierGroupsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerModifierGroupsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data101.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerModifierGroupsResponseToJson(
  PostV1OwnerModifierGroupsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
