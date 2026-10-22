// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'room_fee2.dart';
import 'place_fee2.dart';
import 'min_spend_topup2.dart';
import 'service_charge2.dart';
import 'deposit2.dart';
import 'booking_fee2.dart';
import 'credit2.dart';
import 'delivery2.dart';

part 'charges2.g.dart';

@JsonSerializable()
class Charges2 {
  const Charges2({
    required this.roomFee,
    required this.placeFee,
    required this.minSpendTopup,
    required this.serviceCharge,
    required this.deposit,
    required this.bookingFee,
    required this.credit,
    required this.delivery,
  });

  factory Charges2.fromJson(Map<String, Object?> json) =>
      _$Charges2FromJson(json);

  @JsonKey(name: 'room_fee')
  final RoomFee2 roomFee;
  @JsonKey(name: 'place_fee')
  final PlaceFee2 placeFee;
  @JsonKey(name: 'min_spend_topup')
  final MinSpendTopup2 minSpendTopup;
  @JsonKey(name: 'service_charge')
  final ServiceCharge2 serviceCharge;
  final Deposit2 deposit;
  @JsonKey(name: 'booking_fee')
  final BookingFee2 bookingFee;
  final Credit2 credit;
  final Delivery2 delivery;

  Map<String, Object?> toJson() => _$Charges2ToJson(this);
}
