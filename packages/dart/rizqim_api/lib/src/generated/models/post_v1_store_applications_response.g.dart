// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_store_applications_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1StoreApplicationsResponse _$PostV1StoreApplicationsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1StoreApplicationsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data264.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1StoreApplicationsResponseToJson(
  PostV1StoreApplicationsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
