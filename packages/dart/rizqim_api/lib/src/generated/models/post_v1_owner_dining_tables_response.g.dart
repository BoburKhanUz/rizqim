// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_dining_tables_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerDiningTablesResponse _$PostV1OwnerDiningTablesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerDiningTablesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data72.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerDiningTablesResponseToJson(
  PostV1OwnerDiningTablesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
