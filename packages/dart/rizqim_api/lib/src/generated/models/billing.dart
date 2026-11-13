// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order5.dart';
import 'bills.dart';
import 'refunds.dart';

part 'billing.g.dart';

@JsonSerializable()
class Billing {
  const Billing({
    required this.order,
    required this.bills,
    required this.refunds,
  });

  factory Billing.fromJson(Map<String, Object?> json) =>
      _$BillingFromJson(json);

  final Order5 order;
  final List<Bills> bills;
  final List<Refunds> refunds;

  Map<String, Object?> toJson() => _$BillingToJson(this);
}
