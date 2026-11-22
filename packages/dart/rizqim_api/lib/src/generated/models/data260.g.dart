// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data260.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data260 _$Data260FromJson(Map<String, dynamic> json) => Data260(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data260ToJson(Data260 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
