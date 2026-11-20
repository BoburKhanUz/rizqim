// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data268.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data268 _$Data268FromJson(Map<String, dynamic> json) => Data268(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal31.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data268ToJson(Data268 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
