// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data244.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data244 _$Data244FromJson(Map<String, dynamic> json) => Data244(
  event: Event11.fromJson(json['event'] as Map<String, dynamic>),
  guests: Guests4.fromJson(json['guests'] as Map<String, dynamic>),
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines24.fromJson(e as Map<String, dynamic>))
      .toList(),
  packageTotal: json['package_total'] as String,
  hallTotal: json['hall_total'] as String,
  extrasTotal: json['extras_total'] as String,
  orders: Orders4.fromJson(json['orders'] as Map<String, dynamic>),
  total: json['total'] as String,
  advances: Advances3.fromJson(json['advances'] as Map<String, dynamic>),
  due: json['due'] as String,
  excess: json['excess'] as String,
  settlement: json['settlement'] == null
      ? null
      : Settlement5.fromJson(json['settlement'] as Map<String, dynamic>),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings19.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data244ToJson(Data244 instance) => <String, dynamic>{
  'event': instance.event,
  'guests': instance.guests,
  'lines': instance.lines,
  'package_total': instance.packageTotal,
  'hall_total': instance.hallTotal,
  'extras_total': instance.extrasTotal,
  'orders': instance.orders,
  'total': instance.total,
  'advances': instance.advances,
  'due': instance.due,
  'excess': instance.excess,
  'settlement': instance.settlement,
  'warnings': instance.warnings,
};
