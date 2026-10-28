// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data127.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data127 _$Data127FromJson(Map<String, dynamic> json) => Data127(
  booking: Booking.fromJson(json['booking'] as Map<String, dynamic>),
  order: Order7.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data127ToJson(Data127 instance) => <String, dynamic>{
  'booking': instance.booking,
  'order': instance.order,
};
