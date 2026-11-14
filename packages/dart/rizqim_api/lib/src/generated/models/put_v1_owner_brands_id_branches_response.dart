// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'put_v1_owner_brands_id_branches_response.g.dart';

@JsonSerializable()
class PutV1OwnerBrandsIdBranchesResponse {
  const PutV1OwnerBrandsIdBranchesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerBrandsIdBranchesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerBrandsIdBranchesResponseFromJson(json);

  final bool success;
  final String message;
  final String data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerBrandsIdBranchesResponseToJson(this);
}
