// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_brands_id_branches_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBrandsIdBranchesRequestBody _$V1OwnerBrandsIdBranchesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBrandsIdBranchesRequestBody(
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$V1OwnerBrandsIdBranchesRequestBodyToJson(
  V1OwnerBrandsIdBranchesRequestBody instance,
) => <String, dynamic>{'branches': instance.branches};
