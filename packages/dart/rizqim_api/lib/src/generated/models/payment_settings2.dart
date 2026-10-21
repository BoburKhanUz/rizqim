// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'working_hours6.dart';
import 'cash.dart';
import 'click.dart';
import 'payme.dart';
import 'uzum.dart';
import 'paynet.dart';

part 'payment_settings2.g.dart';

@JsonSerializable()
class PaymentSettings2 {
  const PaymentSettings2({
    this.deliveryFee,
    this.deliveryEnabled,
    this.pickupEnabled,
    this.workingHours,
    this.cash,
    this.click,
    this.payme,
    this.uzum,
    this.paynet,
  });

  factory PaymentSettings2.fromJson(Map<String, Object?> json) =>
      _$PaymentSettings2FromJson(json);

  @JsonKey(name: 'delivery_fee')
  final num? deliveryFee;
  @JsonKey(name: 'delivery_enabled')
  final bool? deliveryEnabled;
  @JsonKey(name: 'pickup_enabled')
  final bool? pickupEnabled;
  @JsonKey(name: 'working_hours')
  final WorkingHours6? workingHours;
  final Cash? cash;
  final Click? click;
  final Payme? payme;
  final Uzum? uzum;
  final Paynet? paynet;

  Map<String, Object?> toJson() => _$PaymentSettings2ToJson(this);
}
