// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'room_fee3.dart';
import 'place_fee3.dart';
import 'min_spend_topup3.dart';
import 'service_charge3.dart';
import 'deposit3.dart';
import 'booking_fee3.dart';
import 'credit3.dart';
import 'delivery3.dart';

part 'charges3.g.dart';

@JsonSerializable()
class Charges3 {
  const Charges3({
    this.roomFee,
    this.placeFee,
    this.minSpendTopup,
    this.serviceCharge,
    this.deposit,
    this.bookingFee,
    this.credit,
    this.delivery,
  });

  factory Charges3.fromJson(Map<String, Object?> json) =>
      _$Charges3FromJson(json);

  @JsonKey(name: 'room_fee')
  final RoomFee3? roomFee;
  @JsonKey(name: 'place_fee')
  final PlaceFee3? placeFee;
  @JsonKey(name: 'min_spend_topup')
  final MinSpendTopup3? minSpendTopup;
  @JsonKey(name: 'service_charge')
  final ServiceCharge3? serviceCharge;
  final Deposit3? deposit;
  @JsonKey(name: 'booking_fee')
  final BookingFee3? bookingFee;
  final Credit3? credit;
  final Delivery3? delivery;

  Map<String, Object?> toJson() => _$Charges3ToJson(this);
}
