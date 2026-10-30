// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data197.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data197 _$Data197FromJson(Map<String, dynamic> json) => Data197(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal8.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data197ToJson(Data197 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
