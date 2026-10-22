// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_printers_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerPrintersIdResponse _$PatchV1OwnerPrintersIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerPrintersIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data93.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerPrintersIdResponseToJson(
  PatchV1OwnerPrintersIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
