// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data114.dart';

part 'put_v1_owner_pos_service_charge_rules_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosServiceChargeRulesResponse {
  const PutV1OwnerPosServiceChargeRulesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosServiceChargeRulesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerPosServiceChargeRulesResponseFromJson(json);

  final bool success;
  final String message;
  final Data114 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerPosServiceChargeRulesResponseToJson(this);
}
