// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'extra_bills4.dart';

part 'settlement6.g.dart';

@JsonSerializable()
class Settlement6 {
  const Settlement6({
    required this.uuid,
    required this.status,
    required this.billUuid,
    required this.appliedAmount,
    required this.excessAmount,
    required this.excessStatus,
    required this.settledAt,
    required this.extraBills,
    required this.extraBillsNotice,
  });

  factory Settlement6.fromJson(Map<String, Object?> json) =>
      _$Settlement6FromJson(json);

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
  @JsonKey(name: 'extra_bills')
  final List<ExtraBills4> extraBills;
  @JsonKey(name: 'extra_bills_notice')
  final String? extraBillsNotice;

  Map<String, Object?> toJson() => _$Settlement6ToJson(this);
}
