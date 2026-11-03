// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data51.dart';

part 'get_v1_owner_branches_id_response.g.dart';

@JsonSerializable()
class GetV1OwnerBranchesIdResponse {
  const GetV1OwnerBranchesIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBranchesIdResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerBranchesIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data51 data;

  Map<String, Object?> toJson() => _$GetV1OwnerBranchesIdResponseToJson(this);
}
