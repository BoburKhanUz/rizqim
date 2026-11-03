// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data49.dart';

part 'put_v1_owner_branches_branch_pickup_settings_response.g.dart';

@JsonSerializable()
class PutV1OwnerBranchesBranchPickupSettingsResponse {
  const PutV1OwnerBranchesBranchPickupSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerBranchesBranchPickupSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerBranchesBranchPickupSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data49 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerBranchesBranchPickupSettingsResponseToJson(this);
}
