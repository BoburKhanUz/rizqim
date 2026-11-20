// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data267.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data267 _$Data267FromJson(Map<String, dynamic> json) => Data267(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal30.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data267ToJson(Data267 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
