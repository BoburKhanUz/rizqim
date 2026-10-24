// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data131.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data131 _$Data131FromJson(Map<String, dynamic> json) => Data131(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal5.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data131ToJson(Data131 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
