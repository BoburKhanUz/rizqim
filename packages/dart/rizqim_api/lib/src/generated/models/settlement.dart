// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'settlement.g.dart';

@JsonSerializable()
class Settlement {
  const Settlement({
    required this.uuid,
    required this.status,
    required this.billUuid,
    required this.appliedAmount,
    required this.excessAmount,
    required this.excessStatus,
    required this.settledAt,
  });

  factory Settlement.fromJson(Map<String, Object?> json) =>
      _$SettlementFromJson(json);

  final String uuid;
  final String status;
  @JsonKey(name: 'bill_uuid')
  final String? billUuid;
  @JsonKey(name: 'applied_amount')
  final String appliedAmount;
  @JsonKey(name: 'excess_amount')
  final String excessAmount;
  @JsonKey(name: 'excess_status')
  final String excessStatus;
  @JsonKey(name: 'settled_at')
  final String? settledAt;

  Map<String, Object?> toJson() => _$SettlementToJson(this);
}
