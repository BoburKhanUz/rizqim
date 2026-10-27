// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_dining_tables_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerDiningTablesIdResponse _$PatchV1OwnerDiningTablesIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerDiningTablesIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data75.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerDiningTablesIdResponseToJson(
  PatchV1OwnerDiningTablesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
