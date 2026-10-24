// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_superadmin_stores_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1SuperadminStoresResponse _$PostV1SuperadminStoresResponseFromJson(
  Map<String, dynamic> json,
) => PostV1SuperadminStoresResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data194.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1SuperadminStoresResponseToJson(
  PostV1SuperadminStoresResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
