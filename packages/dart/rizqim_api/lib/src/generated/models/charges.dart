// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'room_fee.dart';
import 'place_fee.dart';
import 'min_spend_topup.dart';
import 'service_charge.dart';
import 'deposit.dart';
import 'booking_fee.dart';
import 'credit.dart';
import 'delivery.dart';

part 'charges.g.dart';

@JsonSerializable()
class Charges {
  const Charges({
    required this.roomFee,
    required this.placeFee,
    required this.minSpendTopup,
    required this.serviceCharge,
    required this.deposit,
    required this.bookingFee,
    required this.credit,
    required this.delivery,
  });

  factory Charges.fromJson(Map<String, Object?> json) =>
      _$ChargesFromJson(json);

  @JsonKey(name: 'room_fee')
  final RoomFee roomFee;
  @JsonKey(name: 'place_fee')
  final PlaceFee placeFee;
  @JsonKey(name: 'min_spend_topup')
  final MinSpendTopup minSpendTopup;
  @JsonKey(name: 'service_charge')
  final ServiceCharge serviceCharge;
  final Deposit deposit;
  @JsonKey(name: 'booking_fee')
  final BookingFee bookingFee;
  final Credit credit;
  final Delivery delivery;

  Map<String, Object?> toJson() => _$ChargesToJson(this);
}
