// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data265.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data265 _$Data265FromJson(Map<String, dynamic> json) => Data265(
  booking: Booking.fromJson(json['booking'] as Map<String, dynamic>),
  order: Order9.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data265ToJson(Data265 instance) => <String, dynamic>{
  'booking': instance.booking,
  'order': instance.order,
};
