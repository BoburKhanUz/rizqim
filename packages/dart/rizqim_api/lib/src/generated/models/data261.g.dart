// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data261.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data261 _$Data261FromJson(Map<String, dynamic> json) => Data261(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data261ToJson(Data261 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
