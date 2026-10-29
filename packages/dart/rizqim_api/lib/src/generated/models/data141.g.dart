// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data141.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data141 _$Data141FromJson(Map<String, dynamic> json) => Data141(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data141ToJson(Data141 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
