// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data122.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data122 _$Data122FromJson(Map<String, dynamic> json) => Data122(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data122ToJson(Data122 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
