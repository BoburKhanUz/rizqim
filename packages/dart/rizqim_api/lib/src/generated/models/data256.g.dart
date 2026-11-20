// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data256.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data256 _$Data256FromJson(Map<String, dynamic> json) => Data256(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data256ToJson(Data256 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
