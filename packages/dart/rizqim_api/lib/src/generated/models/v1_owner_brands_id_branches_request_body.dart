// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branches2.dart';

part 'v1_owner_brands_id_branches_request_body.g.dart';

@JsonSerializable()
class V1OwnerBrandsIdBranchesRequestBody {
  const V1OwnerBrandsIdBranchesRequestBody({required this.branches});

  factory V1OwnerBrandsIdBranchesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBrandsIdBranchesRequestBodyFromJson(json);

  final List<Branches2> branches;

  Map<String, Object?> toJson() =>
      _$V1OwnerBrandsIdBranchesRequestBodyToJson(this);
}
