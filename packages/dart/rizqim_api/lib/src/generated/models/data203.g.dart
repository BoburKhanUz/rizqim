// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data203.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data203 _$Data203FromJson(Map<String, dynamic> json) => Data203(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal9.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data203ToJson(Data203 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
