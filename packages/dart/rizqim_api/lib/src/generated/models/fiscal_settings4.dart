// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'charges4.dart';
import 'sandbox4.dart';

part 'fiscal_settings4.g.dart';

@JsonSerializable()
class FiscalSettings4 {
  const FiscalSettings4({
    required this.enabled,
    required this.driver,
    required this.activeDriver,
    required this.charges,
    required this.sandbox,
    required this.depositRefundReceipt,
    required this.retainedDepositReceipt,
  });

  factory FiscalSettings4.fromJson(Map<String, Object?> json) =>
      _$FiscalSettings4FromJson(json);

  final bool enabled;
  final String driver;
  @JsonKey(name: 'active_driver')
  final String activeDriver;
  final Map<String, Charges4> charges;
  final Sandbox4 sandbox;
  @JsonKey(name: 'deposit_refund_receipt')
  final bool? depositRefundReceipt;
  @JsonKey(name: 'retained_deposit_receipt')
  final bool? retainedDepositReceipt;

  Map<String, Object?> toJson() => _$FiscalSettings4ToJson(this);
}
