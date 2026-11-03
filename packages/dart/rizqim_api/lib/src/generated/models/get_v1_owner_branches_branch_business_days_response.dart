// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data43.dart';

part 'get_v1_owner_branches_branch_business_days_response.g.dart';

@JsonSerializable()
class GetV1OwnerBranchesBranchBusinessDaysResponse {
  const GetV1OwnerBranchesBranchBusinessDaysResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBranchesBranchBusinessDaysResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerBranchesBranchBusinessDaysResponseFromJson(json);

  final bool success;
  final String message;
  final Data43 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerBranchesBranchBusinessDaysResponseToJson(this);
}
