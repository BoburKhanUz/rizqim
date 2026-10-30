// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_combos_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerCombosResponse _$PostV1OwnerCombosResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerCombosResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data60.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerCombosResponseToJson(
  PostV1OwnerCombosResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
