// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_combos_id_preview_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerCombosIdPreviewResponse _$PostV1OwnerCombosIdPreviewResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerCombosIdPreviewResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data62.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerCombosIdPreviewResponseToJson(
  PostV1OwnerCombosIdPreviewResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
