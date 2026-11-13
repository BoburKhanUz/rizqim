// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data212.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data212 _$Data212FromJson(Map<String, dynamic> json) => Data212(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal13.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data212ToJson(Data212 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
