// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data264.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data264 _$Data264FromJson(Map<String, dynamic> json) => Data264(
  booking: Booking3.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment2.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal28.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data264ToJson(Data264 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
