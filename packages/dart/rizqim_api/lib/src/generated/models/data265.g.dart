// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data265.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data265 _$Data265FromJson(Map<String, dynamic> json) => Data265(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal29.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data265ToJson(Data265 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
