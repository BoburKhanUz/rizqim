// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data287.dart';

part 'get_v1_stores_store_branches_branch_pickup_slots_response.g.dart';

@JsonSerializable()
class GetV1StoresStoreBranchesBranchPickupSlotsResponse {
  const GetV1StoresStoreBranchesBranchPickupSlotsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1StoresStoreBranchesBranchPickupSlotsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1StoresStoreBranchesBranchPickupSlotsResponseFromJson(json);

  final bool success;
  final String message;
  final Data287 data;

  Map<String, Object?> toJson() =>
      _$GetV1StoresStoreBranchesBranchPickupSlotsResponseToJson(this);
}
