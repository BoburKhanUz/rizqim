// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'charges3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Charges3 _$Charges3FromJson(Map<String, dynamic> json) => Charges3(
  roomFee: json['room_fee'] == null
      ? null
      : RoomFee3.fromJson(json['room_fee'] as Map<String, dynamic>),
  placeFee: json['place_fee'] == null
      ? null
      : PlaceFee3.fromJson(json['place_fee'] as Map<String, dynamic>),
  minSpendTopup: json['min_spend_topup'] == null
      ? null
      : MinSpendTopup3.fromJson(
          json['min_spend_topup'] as Map<String, dynamic>,
        ),
  serviceCharge: json['service_charge'] == null
      ? null
      : ServiceCharge3.fromJson(json['service_charge'] as Map<String, dynamic>),
  deposit: json['deposit'] == null
      ? null
      : Deposit3.fromJson(json['deposit'] as Map<String, dynamic>),
  bookingFee: json['booking_fee'] == null
      ? null
      : BookingFee3.fromJson(json['booking_fee'] as Map<String, dynamic>),
  credit: json['credit'] == null
      ? null
      : Credit3.fromJson(json['credit'] as Map<String, dynamic>),
  delivery: json['delivery'] == null
      ? null
      : Delivery3.fromJson(json['delivery'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Charges3ToJson(Charges3 instance) => <String, dynamic>{
  'room_fee': instance.roomFee,
  'place_fee': instance.placeFee,
  'min_spend_topup': instance.minSpendTopup,
  'service_charge': instance.serviceCharge,
  'deposit': instance.deposit,
  'booking_fee': instance.bookingFee,
  'credit': instance.credit,
  'delivery': instance.delivery,
};
