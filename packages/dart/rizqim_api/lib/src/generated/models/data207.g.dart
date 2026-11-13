// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data207.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data207 _$Data207FromJson(Map<String, dynamic> json) => Data207(
  booking: Booking.fromJson(json['booking'] as Map<String, dynamic>),
  order: Order9.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data207ToJson(Data207 instance) => <String, dynamic>{
  'booking': instance.booking,
  'order': instance.order,
};
