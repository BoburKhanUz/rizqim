// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data270.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data270 _$Data270FromJson(Map<String, dynamic> json) => Data270(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal29.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data270ToJson(Data270 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
