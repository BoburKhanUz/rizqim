// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data130.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data130 _$Data130FromJson(Map<String, dynamic> json) => Data130(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal6.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data130ToJson(Data130 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
