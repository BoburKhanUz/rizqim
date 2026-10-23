// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_waiter5.g.dart';

@JsonSerializable()
class ByWaiter5 {
  const ByWaiter5({
    required this.waiterId,
    required this.name,
    required this.orders,
    required this.bills,
    required this.amount,
    required this.serviceCharge,
  });

  factory ByWaiter5.fromJson(Map<String, Object?> json) =>
      _$ByWaiter5FromJson(json);

  @JsonKey(name: 'waiter_id')
  final int? waiterId;
  final String? name;
  final int orders;
  final int bills;
  final String amount;
  @JsonKey(name: 'service_charge')
  final String serviceCharge;

  Map<String, Object?> toJson() => _$ByWaiter5ToJson(this);
}
