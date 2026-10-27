// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data126.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data126 _$Data126FromJson(Map<String, dynamic> json) => Data126(
  booking: Booking.fromJson(json['booking'] as Map<String, dynamic>),
  order: Order7.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data126ToJson(Data126 instance) => <String, dynamic>{
  'booking': instance.booking,
  'order': instance.order,
};
