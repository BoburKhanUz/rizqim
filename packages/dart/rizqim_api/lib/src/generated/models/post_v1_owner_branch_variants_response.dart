// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch_product_variant.dart';

part 'post_v1_owner_branch_variants_response.g.dart';

@JsonSerializable()
class PostV1OwnerBranchVariantsResponse {
  const PostV1OwnerBranchVariantsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerBranchVariantsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerBranchVariantsResponseFromJson(json);

  final bool success;
  final String message;
  final BranchProductVariant data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerBranchVariantsResponseToJson(this);
}
