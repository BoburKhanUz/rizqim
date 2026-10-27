// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data132.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data132 _$Data132FromJson(Map<String, dynamic> json) => Data132(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal6.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data132ToJson(Data132 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
