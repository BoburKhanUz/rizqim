// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data186.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data186 _$Data186FromJson(Map<String, dynamic> json) => Data186(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data186ToJson(Data186 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
