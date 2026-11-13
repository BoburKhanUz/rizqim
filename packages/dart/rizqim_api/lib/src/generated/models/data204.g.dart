// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data204.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data204 _$Data204FromJson(Map<String, dynamic> json) => Data204(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal10.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data204ToJson(Data204 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
