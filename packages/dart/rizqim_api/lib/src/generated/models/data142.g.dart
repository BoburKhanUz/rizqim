// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data142.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data142 _$Data142FromJson(Map<String, dynamic> json) => Data142(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data142ToJson(Data142 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
