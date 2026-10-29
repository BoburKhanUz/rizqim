// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data170.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data170 _$Data170FromJson(Map<String, dynamic> json) => Data170(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data170ToJson(Data170 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
