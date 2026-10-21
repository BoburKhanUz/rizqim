// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'charges2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Charges2 _$Charges2FromJson(Map<String, dynamic> json) => Charges2(
  roomFee: RoomFee2.fromJson(json['room_fee'] as Map<String, dynamic>),
  placeFee: PlaceFee2.fromJson(json['place_fee'] as Map<String, dynamic>),
  minSpendTopup: MinSpendTopup2.fromJson(
    json['min_spend_topup'] as Map<String, dynamic>,
  ),
  serviceCharge: ServiceCharge2.fromJson(
    json['service_charge'] as Map<String, dynamic>,
  ),
  deposit: Deposit2.fromJson(json['deposit'] as Map<String, dynamic>),
  bookingFee: BookingFee2.fromJson(json['booking_fee'] as Map<String, dynamic>),
  credit: Credit2.fromJson(json['credit'] as Map<String, dynamic>),
  delivery: Delivery2.fromJson(json['delivery'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Charges2ToJson(Charges2 instance) => <String, dynamic>{
  'room_fee': instance.roomFee,
  'place_fee': instance.placeFee,
  'min_spend_topup': instance.minSpendTopup,
  'service_charge': instance.serviceCharge,
  'deposit': instance.deposit,
  'booking_fee': instance.bookingFee,
  'credit': instance.credit,
  'delivery': instance.delivery,
};
