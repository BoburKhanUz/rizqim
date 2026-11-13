// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data213.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data213 _$Data213FromJson(Map<String, dynamic> json) => Data213(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal14.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data213ToJson(Data213 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
