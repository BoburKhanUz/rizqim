// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data37.dart';

part 'get_v1_owner_branches_response.g.dart';

@JsonSerializable()
class GetV1OwnerBranchesResponse {
  const GetV1OwnerBranchesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBranchesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerBranchesResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data37> data;

  Map<String, Object?> toJson() => _$GetV1OwnerBranchesResponseToJson(this);
}
