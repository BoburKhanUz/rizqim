// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'daily.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Daily _$DailyFromJson(Map<String, dynamic> json) => Daily(
  date: json['date'] as String,
  orders: (json['orders'] as num).toInt(),
  delivered: (json['delivered'] as num).toInt(),
  revenue: json['revenue'] as num,
);

Map<String, dynamic> _$DailyToJson(Daily instance) => <String, dynamic>{
  'date': instance.date,
  'orders': instance.orders,
  'delivered': instance.delivered,
  'revenue': instance.revenue,
};
