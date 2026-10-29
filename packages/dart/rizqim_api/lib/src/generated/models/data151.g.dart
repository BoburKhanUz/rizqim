// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data151.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data151 _$Data151FromJson(Map<String, dynamic> json) => Data151(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal6.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data151ToJson(Data151 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
