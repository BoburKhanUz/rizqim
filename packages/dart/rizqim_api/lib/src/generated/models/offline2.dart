// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fees.dart';
import 'places.dart';
import 'guest_items.dart';
import 'limits4.dart';

part 'offline2.g.dart';

@JsonSerializable()
class Offline2 {
  const Offline2({
    required this.devicePrefix,
    required this.nextLocalNumber,
    required this.businessDate,
    required this.dayType,
    required this.serviceChargeLocked,
    required this.fees,
    required this.places,
    required this.guestItems,
    required this.limits,
  });

  factory Offline2.fromJson(Map<String, Object?> json) =>
      _$Offline2FromJson(json);

  @JsonKey(name: 'device_prefix')
  final String? devicePrefix;
  @JsonKey(name: 'next_local_number')
  final int nextLocalNumber;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'day_type')
  final String dayType;
  @JsonKey(name: 'service_charge_locked')
  final bool serviceChargeLocked;
  final List<Fees> fees;
  final List<Places> places;
  @JsonKey(name: 'guest_items')
  final List<GuestItems> guestItems;
  final Limits4 limits;

  Map<String, Object?> toJson() => _$Offline2ToJson(this);
}
