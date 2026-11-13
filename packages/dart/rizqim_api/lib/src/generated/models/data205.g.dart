// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data205.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data205 _$Data205FromJson(Map<String, dynamic> json) => Data205(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal11.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data205ToJson(Data205 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
