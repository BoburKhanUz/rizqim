// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data202.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data202 _$Data202FromJson(Map<String, dynamic> json) => Data202(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data202ToJson(Data202 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
