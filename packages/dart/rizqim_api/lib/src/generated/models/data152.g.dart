// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data152.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data152 _$Data152FromJson(Map<String, dynamic> json) => Data152(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal7.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data152ToJson(Data152 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
