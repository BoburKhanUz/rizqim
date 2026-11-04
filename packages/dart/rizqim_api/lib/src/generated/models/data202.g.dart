// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data202.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data202 _$Data202FromJson(Map<String, dynamic> json) => Data202(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal8.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data202ToJson(Data202 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
