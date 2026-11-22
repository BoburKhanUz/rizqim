// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data271.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data271 _$Data271FromJson(Map<String, dynamic> json) => Data271(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal30.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data271ToJson(Data271 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
