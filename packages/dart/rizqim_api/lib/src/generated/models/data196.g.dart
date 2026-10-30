// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data196.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data196 _$Data196FromJson(Map<String, dynamic> json) => Data196(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal7.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data196ToJson(Data196 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
