// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data265.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data265 _$Data265FromJson(Map<String, dynamic> json) => Data265(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal30.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data265ToJson(Data265 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
