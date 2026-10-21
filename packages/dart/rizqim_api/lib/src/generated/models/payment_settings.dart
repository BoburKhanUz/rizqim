// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'working_hours5.dart';

part 'payment_settings.g.dart';

@JsonSerializable()
class PaymentSettings {
  const PaymentSettings({
    this.cash,
    this.click,
    this.payme,
    this.uzum,
    this.paynet,
    this.deliveryFee,
    this.deliveryEnabled,
    this.pickupEnabled,
    this.workingHours,
    this.allowedMethods,
  });

  factory PaymentSettings.fromJson(Map<String, Object?> json) =>
      _$PaymentSettingsFromJson(json);

  final dynamic cash;
  final dynamic click;
  final dynamic payme;
  final dynamic uzum;
  final dynamic paynet;
  @JsonKey(name: 'delivery_fee')
  final dynamic deliveryFee;
  @JsonKey(name: 'delivery_enabled')
  final bool? deliveryEnabled;
  @JsonKey(name: 'pickup_enabled')
  final bool? pickupEnabled;
  @JsonKey(name: 'working_hours')
  final WorkingHours5? workingHours;
  @JsonKey(name: 'allowed_methods')
  final List<String>? allowedMethods;

  Map<String, Object?> toJson() => _$PaymentSettingsToJson(this);
}
