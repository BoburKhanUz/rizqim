// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data37.dart';

part 'post_v1_owner_branches_response.g.dart';

@JsonSerializable()
class PostV1OwnerBranchesResponse {
  const PostV1OwnerBranchesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerBranchesResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerBranchesResponseFromJson(json);

  final bool success;
  final String message;
  final Data37 data;

  Map<String, Object?> toJson() => _$PostV1OwnerBranchesResponseToJson(this);
}
