// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data187.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data187 _$Data187FromJson(Map<String, dynamic> json) => Data187(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data187ToJson(Data187 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
