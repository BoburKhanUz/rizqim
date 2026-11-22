// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data259.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data259 _$Data259FromJson(Map<String, dynamic> json) => Data259(
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data259ToJson(Data259 instance) => <String, dynamic>{
  'bookings': instance.bookings,
};
