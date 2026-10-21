// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_stop_list_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStopListResponse _$PostV1OwnerStopListResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerStopListResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data77.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerStopListResponseToJson(
  PostV1OwnerStopListResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
