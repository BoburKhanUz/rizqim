// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data269.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data269 _$Data269FromJson(Map<String, dynamic> json) => Data269(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal31.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data269ToJson(Data269 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
