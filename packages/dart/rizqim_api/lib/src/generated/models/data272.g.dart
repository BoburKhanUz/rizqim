// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data272.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data272 _$Data272FromJson(Map<String, dynamic> json) => Data272(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal31.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data272ToJson(Data272 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
