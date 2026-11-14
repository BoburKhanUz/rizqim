// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_brands_id_branches_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerBrandsIdBranchesResponse _$PutV1OwnerBrandsIdBranchesResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerBrandsIdBranchesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as String,
);

Map<String, dynamic> _$PutV1OwnerBrandsIdBranchesResponseToJson(
  PutV1OwnerBrandsIdBranchesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
