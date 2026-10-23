// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_waiter.g.dart';

@JsonSerializable()
class ByWaiter {
  const ByWaiter({
    required this.waiterId,
    required this.name,
    required this.orders,
    required this.bills,
    required this.amount,
    required this.serviceCharge,
  });

  factory ByWaiter.fromJson(Map<String, Object?> json) =>
      _$ByWaiterFromJson(json);

  @JsonKey(name: 'waiter_id')
  final int? waiterId;
  final String? name;
  final int orders;
  final int bills;
  final String amount;
  @JsonKey(name: 'service_charge')
  final String serviceCharge;

  Map<String, Object?> toJson() => _$ByWaiterToJson(this);
}
