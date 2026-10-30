// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data181.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data181 _$Data181FromJson(Map<String, dynamic> json) => Data181(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal7.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data181ToJson(Data181 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
