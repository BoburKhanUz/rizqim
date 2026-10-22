// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data121.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data121 _$Data121FromJson(Map<String, dynamic> json) => Data121(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data121ToJson(Data121 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
