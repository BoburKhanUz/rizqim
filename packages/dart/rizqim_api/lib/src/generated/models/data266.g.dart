// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data266.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data266 _$Data266FromJson(Map<String, dynamic> json) => Data266(
  booking: Booking2.fromJson(json['booking'] as Map<String, dynamic>),
  payment: Payment.fromJson(json['payment'] as Map<String, dynamic>),
  fiscal: Fiscal29.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data266ToJson(Data266 instance) => <String, dynamic>{
  'booking': instance.booking,
  'payment': instance.payment,
  'fiscal': instance.fiscal,
};
