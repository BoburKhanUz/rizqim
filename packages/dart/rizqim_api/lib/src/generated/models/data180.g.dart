// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data180.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data180 _$Data180FromJson(Map<String, dynamic> json) => Data180(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal6.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data180ToJson(Data180 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
