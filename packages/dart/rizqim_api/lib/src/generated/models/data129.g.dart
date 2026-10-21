// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data129.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data129 _$Data129FromJson(Map<String, dynamic> json) => Data129(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data129ToJson(Data129 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
};
