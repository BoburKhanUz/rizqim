// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data54.dart';

part 'put_v1_owner_branches_id_booking_rules_response.g.dart';

@JsonSerializable()
class PutV1OwnerBranchesIdBookingRulesResponse {
  const PutV1OwnerBranchesIdBookingRulesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerBranchesIdBookingRulesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerBranchesIdBookingRulesResponseFromJson(json);

  final bool success;
  final String message;
  final Data54 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerBranchesIdBookingRulesResponseToJson(this);
}
