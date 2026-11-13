// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data214.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data214 _$Data214FromJson(Map<String, dynamic> json) => Data214(
  booking: Booking4.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment3.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal15.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data214ToJson(Data214 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
