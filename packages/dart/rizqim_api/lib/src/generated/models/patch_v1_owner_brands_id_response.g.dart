// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_brands_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerBrandsIdResponse _$PatchV1OwnerBrandsIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerBrandsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as String,
);

Map<String, dynamic> _$PatchV1OwnerBrandsIdResponseToJson(
  PatchV1OwnerBrandsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
