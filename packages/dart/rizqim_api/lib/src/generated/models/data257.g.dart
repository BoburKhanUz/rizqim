// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data257.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data257 _$Data257FromJson(Map<String, dynamic> json) => Data257(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data257ToJson(Data257 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
