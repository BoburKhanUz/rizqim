// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data199.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data199 _$Data199FromJson(Map<String, dynamic> json) => Data199(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal9.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data199ToJson(Data199 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
