// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_sync_import_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosSyncImportResponse _$PostV1OwnerPosSyncImportResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerPosSyncImportResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data161.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerPosSyncImportResponseToJson(
  PostV1OwnerPosSyncImportResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
