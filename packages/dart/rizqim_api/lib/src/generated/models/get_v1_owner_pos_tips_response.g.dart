// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_tips_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosTipsResponse _$GetV1OwnerPosTipsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosTipsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data115.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosTipsResponseToJson(
  GetV1OwnerPosTipsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
