// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data153.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data153 _$Data153FromJson(Map<String, dynamic> json) => Data153(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal6.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data153ToJson(Data153 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
