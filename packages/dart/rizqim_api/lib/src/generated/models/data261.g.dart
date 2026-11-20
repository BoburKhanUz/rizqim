// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data261.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data261 _$Data261FromJson(Map<String, dynamic> json) => Data261(
  booking: Booking.fromJson(json['booking'] as Map<String, dynamic>),
  order: Order9.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data261ToJson(Data261 instance) => <String, dynamic>{
  'booking': instance.booking,
  'order': instance.order,
};
