// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data271.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data271 _$Data271FromJson(Map<String, dynamic> json) => Data271(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal31.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data271ToJson(Data271 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
