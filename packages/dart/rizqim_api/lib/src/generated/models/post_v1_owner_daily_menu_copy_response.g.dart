// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_daily_menu_copy_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerDailyMenuCopyResponse _$PostV1OwnerDailyMenuCopyResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerDailyMenuCopyResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data65.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerDailyMenuCopyResponseToJson(
  PostV1OwnerDailyMenuCopyResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
