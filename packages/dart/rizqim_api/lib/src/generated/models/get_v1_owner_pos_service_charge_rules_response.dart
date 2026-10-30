// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data128.dart';

part 'get_v1_owner_pos_service_charge_rules_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosServiceChargeRulesResponse {
  const GetV1OwnerPosServiceChargeRulesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosServiceChargeRulesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerPosServiceChargeRulesResponseFromJson(json);

  final bool success;
  final String message;
  final Data128 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosServiceChargeRulesResponseToJson(this);
}
