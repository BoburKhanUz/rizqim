// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_staff_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerStaffIdResponse _$PatchV1OwnerStaffIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerStaffIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data132.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerStaffIdResponseToJson(
  PatchV1OwnerStaffIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
