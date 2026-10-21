// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'charges.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Charges _$ChargesFromJson(Map<String, dynamic> json) => Charges(
  roomFee: RoomFee.fromJson(json['room_fee'] as Map<String, dynamic>),
  placeFee: PlaceFee.fromJson(json['place_fee'] as Map<String, dynamic>),
  minSpendTopup: MinSpendTopup.fromJson(
    json['min_spend_topup'] as Map<String, dynamic>,
  ),
  serviceCharge: ServiceCharge.fromJson(
    json['service_charge'] as Map<String, dynamic>,
  ),
  deposit: Deposit.fromJson(json['deposit'] as Map<String, dynamic>),
  bookingFee: BookingFee.fromJson(json['booking_fee'] as Map<String, dynamic>),
  credit: Credit.fromJson(json['credit'] as Map<String, dynamic>),
  delivery: Delivery.fromJson(json['delivery'] as Map<String, dynamic>),
);

Map<String, dynamic> _$ChargesToJson(Charges instance) => <String, dynamic>{
  'room_fee': instance.roomFee,
  'place_fee': instance.placeFee,
  'min_spend_topup': instance.minSpendTopup,
  'service_charge': instance.serviceCharge,
  'deposit': instance.deposit,
  'booking_fee': instance.bookingFee,
  'credit': instance.credit,
  'delivery': instance.delivery,
};
