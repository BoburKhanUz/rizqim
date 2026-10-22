// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'charges5.dart';
import 'sandbox5.dart';

part 'fiscal_settings5.g.dart';

@JsonSerializable()
class FiscalSettings5 {
  const FiscalSettings5({
    this.enabled,
    this.driver,
    this.charges,
    this.sandbox,
    this.depositRefundReceipt,
    this.retainedDepositReceipt,
  });

  factory FiscalSettings5.fromJson(Map<String, Object?> json) =>
      _$FiscalSettings5FromJson(json);

  final bool? enabled;
  final String? driver;
  final Map<String, Charges5>? charges;
  final Sandbox5? sandbox;
  @JsonKey(name: 'deposit_refund_receipt')
  final bool? depositRefundReceipt;
  @JsonKey(name: 'retained_deposit_receipt')
  final bool? retainedDepositReceipt;

  Map<String, Object?> toJson() => _$FiscalSettings5ToJson(this);
}
