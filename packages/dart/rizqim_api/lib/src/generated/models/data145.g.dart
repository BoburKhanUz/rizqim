// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data145.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data145 _$Data145FromJson(Map<String, dynamic> json) => Data145(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  timezone: json['timezone'] as String,
  dayStartsAt: json['day_starts_at'] as String,
  stationId: (json['station_id'] as num?)?.toInt(),
  summary: Summary2.fromJson(json['summary'] as Map<String, dynamic>),
  orders: Orders.fromJson(json['orders'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations7.fromJson(e as Map<String, dynamic>))
      .toList(),
  products: (json['products'] as List<dynamic>)
      .map((e) => Products6.fromJson(e as Map<String, dynamic>))
      .toList(),
  hours: (json['hours'] as List<dynamic>)
      .map((e) => Hours.fromJson(e as Map<String, dynamic>))
      .toList(),
  expo: json['expo'] == null
      ? null
      : Expo.fromJson(json['expo'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data145ToJson(Data145 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'timezone': instance.timezone,
  'day_starts_at': instance.dayStartsAt,
  'station_id': instance.stationId,
  'summary': instance.summary,
  'orders': instance.orders,
  'stations': instance.stations,
  'products': instance.products,
  'hours': instance.hours,
  'expo': instance.expo,
};
