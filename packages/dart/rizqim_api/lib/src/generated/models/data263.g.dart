// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data263.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data263 _$Data263FromJson(Map<String, dynamic> json) => Data263(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal27.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data263ToJson(Data263 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
