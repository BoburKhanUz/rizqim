// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data53.dart';

part 'get_v1_owner_branches_id_booking_rules_response.g.dart';

@JsonSerializable()
class GetV1OwnerBranchesIdBookingRulesResponse {
  const GetV1OwnerBranchesIdBookingRulesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBranchesIdBookingRulesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerBranchesIdBookingRulesResponseFromJson(json);

  final bool success;
  final String message;
  final Data53 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerBranchesIdBookingRulesResponseToJson(this);
}
