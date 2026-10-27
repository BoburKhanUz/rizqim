// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data154.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data154 _$Data154FromJson(Map<String, dynamic> json) => Data154(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal7.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data154ToJson(Data154 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
