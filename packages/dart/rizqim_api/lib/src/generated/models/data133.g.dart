// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data133.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data133 _$Data133FromJson(Map<String, dynamic> json) => Data133(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal6.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data133ToJson(Data133 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
