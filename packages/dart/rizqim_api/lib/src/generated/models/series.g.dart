// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'series.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Series _$SeriesFromJson(Map<String, dynamic> json) => Series(
  date: json['date'] as String,
  orders: (json['orders'] as num).toInt(),
  delivered: (json['delivered'] as num).toInt(),
  revenue: json['revenue'] as String,
);

Map<String, dynamic> _$SeriesToJson(Series instance) => <String, dynamic>{
  'date': instance.date,
  'orders': instance.orders,
  'delivered': instance.delivered,
  'revenue': instance.revenue,
};
