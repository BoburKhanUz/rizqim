// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data198.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data198 _$Data198FromJson(Map<String, dynamic> json) => Data198(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal8.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data198ToJson(Data198 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
