// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data201.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data201 _$Data201FromJson(Map<String, dynamic> json) => Data201(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal7.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data201ToJson(Data201 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
